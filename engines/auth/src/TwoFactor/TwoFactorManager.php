<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\TwoFactor;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Auth
 * TwoFactorManager — فعال‌سازی/تایید TOTP + کدهای بازیابی (DOC-056 §7).
 *
 * 🔒 secret رمزنگاری‌شده در DB (encrypted)؛ کدهای بازیابی فقط هش.
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class TwoFactorManager
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly EncrypterInterface $crypt,
        private readonly string $table = 'two_factor_secrets',
        private readonly Totp $totp = new Totp(),
        private readonly int $recoveryCount = 8,
    ) {
    }

    /**
     * فعال‌سازی 2FA: ساخت secret + کدهای بازیابی.
     *
     * @return array{secret: string, uri: string, recovery_codes: list<string>}
     */
    public function enable(Authenticatable $user, string $accountName = ''): array
    {
        $secret = Totp::generateSecret();
        $recoveryCodes = $this->generateRecoveryCodes();

        $now = Clock::utcNow()->format('Y-m-d H:i:s');

        $this->db->table($this->table)->updateOrInsert(
            ['user_id' => (string) $user->getAuthIdentifier()],
            [
                'secret' => $this->crypt->encrypt($secret, 'auth-2fa'),
                'recovery_codes' => (string) \json_encode(
                    \array_map(static fn (string $code): string => \password_hash($code, PASSWORD_BCRYPT, ['cost' => 10]), $recoveryCodes),
                    JSON_THROW_ON_ERROR,
                ),
                'confirmed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        return [
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri($secret, $accountName !== '' ? $accountName : 'user-' . $user->getAuthIdentifier()),
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /** تایید با کد معتبر (confirm). */
    public function confirm(Authenticatable $user, string $code): bool
    {
        $secret = $this->secretFor($user);
        if ($secret === null) {
            return false;
        }

        if (!$this->totp->verify($secret, $code)) {
            return false;
        }

        $this->db->table($this->table)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->update(['confirmed_at' => Clock::utcNow()->format('Y-m-d H:i:s')]);

        return true;
    }

    public function verifyTotp(Authenticatable $user, string $code): bool
    {
        $secret = $this->secretFor($user);
        if ($secret === null) {
            return false;
        }

        if ($this->totp->verify($secret, $code)) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    public function isEnabled(Authenticatable $user): bool
    {
        $row = $this->db->table($this->table)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->first();

        return $row !== null && $row['confirmed_at'] !== null;
    }

    public function disable(Authenticatable $user): void
    {
        $this->db->table($this->table)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->delete();
    }

    /** @return list<string> */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < $this->recoveryCount; $i++) {
            $codes[] = \substr(\chunk_split(\strtolower(\bin2hex(\random_bytes(5))), 5, '-'), 0, 11);
        }

        return $codes;
    }

    /** مصرف یک کد بازیابی (یک‌بارمصرف). */
    private function consumeRecoveryCode(Authenticatable $user, string $code): bool
    {
        $row = $this->db->table($this->table)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->first();

        if ($row === null) {
            return false;
        }

        $hashes = \json_decode((string) $row['recovery_codes'], true);
        if (!\is_array($hashes)) {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (\is_string($hash) && \password_verify($code, $hash)) {
                unset($hashes[$index]);
                $this->db->table($this->table)
                    ->where('user_id', (string) $user->getAuthIdentifier())
                    ->update(['recovery_codes' => (string) \json_encode(\array_values($hashes), JSON_THROW_ON_ERROR)]);

                return true;
            }
        }

        return false;
    }

    private function secretFor(Authenticatable $user): ?string
    {
        $row = $this->db->table($this->table)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->first();

        if ($row === null) {
            return null;
        }

        try {
            return $this->crypt->decrypt((string) $row['secret'], 'auth-2fa');
        } catch (\Throwable) {
            return null;
        }
    }
}

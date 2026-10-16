<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Auth\Exceptions\TokenInvalidException;
use Tavaan\Engine\Auth\Jwt\JwtEncoder;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Auth
 * TokenManager — صدور/ابطال/refresh توکن‌ها با **Rotation + Reuse Detection**
 * (DOC-056 §4/§6.1).
 *
 * 🔒 refresh token = payload رمزنگاری‌شده {jti, family, sub} (نه JWT خام).
 * 🔒 استفادهٔ مجدد از jti مصرف‌شده = سرقت احتمالی → revoke کل family.
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class TokenManager
{
    public function __construct(
        private readonly JwtEncoder $jwt,
        private readonly EncrypterInterface $crypt,
        private readonly ConnectionInterface $db,
        private readonly string $table = 'personal_access_tokens',
        private readonly ?DispatcherInterface $events = null,
    ) {
    }

    /**
     * صدور جفت توکن برای کاربر.
     *
     * @param list<string>            $scopes
     * @param array<string, mixed>    $extraClaims
     */
    public function issue(
        Authenticatable $user,
        int $ttl = 3600,
        array $scopes = ['*'],
        array $extraClaims = [],
        ?string $family = null,
    ): TokenPair {
        $family ??= Ulid::generate()->toString();

        $payload = [
            'sub' => (string) $user->getAuthIdentifier(),
            'scopes' => $scopes,
            ...$extraClaims,
        ];

        $accessJti = Ulid::generate()->toString();
        $accessToken = $this->jwt->encode($payload, ['ttl' => $ttl, 'jti' => $accessJti]);

        $refreshJti = Ulid::generate()->toString();
        $refreshToken = $this->crypt->encryptJson([
            'jti' => $refreshJti,
            'family' => $family,
            'sub' => (string) $user->getAuthIdentifier(),
            'iat' => Clock::timestamp(),
        ], 'auth-refresh');

        $now = Clock::utcNow()->format('Y-m-d H:i:s');

        // رکورد access (برای revocation)
        $this->db->table($this->table)->insert([
            'user_id' => (string) $user->getAuthIdentifier(),
            'jti' => $accessJti,
            'family' => $family,
            'type' => 'access',
            'scopes' => (string) \json_encode($scopes, JSON_THROW_ON_ERROR),
            'used' => 0,
            'revoked' => 0,
            'expires_at' => Clock::timestamp() + $ttl,
            'created_at' => $now,
        ]);

        // رکورد refresh
        $this->db->table($this->table)->insert([
            'user_id' => (string) $user->getAuthIdentifier(),
            'jti' => $refreshJti,
            'family' => $family,
            'type' => 'refresh',
            'scopes' => '[]',
            'used' => 0,
            'revoked' => 0,
            'expires_at' => Clock::timestamp() + $this->jwt->refreshTtl(),
            'created_at' => $now,
        ]);

        return new TokenPair($accessToken, $refreshToken, $accessJti, $family, $ttl, $scopes);
    }

    /**
     * احراز با access token (با بررسی revocation).
     *
     * @throws TokenInvalidException
     */
    public function authenticate(string $accessToken): array
    {
        $claims = $this->jwt->decode($accessToken);

        if ($this->isRevoked((string) ($claims['jti'] ?? ''))) {
            throw new TokenInvalidException('auth.token.revoked');
        }

        return $claims;
    }

    /**
     * Refresh با Rotation + Reuse Detection (DOC-056 §6.1).
     *
     * @throws TokenInvalidException
     */
    public function refresh(string $refreshToken, ?Authenticatable $user = null): TokenPair
    {
        try {
            $data = $this->crypt->decryptJson($refreshToken, 'auth-refresh');
        } catch (\Throwable) {
            throw new TokenInvalidException('auth.token.refresh_invalid');
        }

        $jti = (string) ($data['jti'] ?? '');
        $family = (string) ($data['family'] ?? '');
        $subject = (string) ($data['sub'] ?? '');

        if ($jti === '' || $family === '' || $subject === '') {
            throw new TokenInvalidException('auth.token.refresh_invalid');
        }

        $record = $this->db->table($this->table)->where('jti', $jti)->where('type', 'refresh')->first();

        // 🔒 reuse detection: jti مصرف‌شده یا revoke‌شده → کل family باطل
        if ($record === null || (int) $record['revoked'] === 1) {
            $this->revokeFamily($family);
            $this->events?->dispatch('auth.token.reuse_detected', ['family' => $family, 'sub' => $subject]);

            throw new TokenInvalidException('auth.token.reuse_detected');
        }

        if ((int) $record['used'] === 1) {
            $this->revokeFamily($family);
            $this->events?->dispatch('auth.token.reuse_detected', ['family' => $family, 'sub' => $subject]);

            throw new TokenInvalidException('auth.token.reuse_detected');
        }

        if ((int) $record['expires_at'] < Clock::timestamp()) {
            throw new TokenInvalidException('auth.token.refresh_expired');
        }

        // علامت‌گذاری jti به‌عنوان مصرف‌شده (rotation)
        $this->db->table($this->table)->where('jti', $jti)->update(['used' => 1]);

        $authenticatable = $user ?? $this->makeAuthenticatable($subject);

        return $this->issue(
            $authenticatable,
            $this->jwt->ttl(),
            ['*'],
            [],
            family: $family,   // همان family
        );
    }

    /** ابطال یک توکن (با jti). */
    public function revoke(string $tokenId): void
    {
        $this->db->table($this->table)->where('jti', $tokenId)->update(['revoked' => 1]);
    }

    /** ابطال کل family (سرقت/خروج کامل). */
    public function revokeFamily(string $family): void
    {
        $this->db->table($this->table)->where('family', $family)->update(['revoked' => 1]);
    }

    /** ابطال همهٔ توکن‌های یک کاربر (logout all devices). */
    public function revokeAllForUser(int|string $userId): int
    {
        return $this->db->table($this->table)
            ->where('user_id', (string) $userId)
            ->where('revoked', 0)
            ->update(['revoked' => 1]);
    }

    public function isRevoked(string $jti): bool
    {
        if ($jti === '') {
            return true;
        }

        $record = $this->db->table($this->table)->where('jti', $jti)->first();

        // توکن ناشناخته → revoked (fail-closed)
        return $record === null || (int) $record['revoked'] === 1;
    }

    /**
     * فقط توکن‌های «ثبت‌شده و revoked» → برای revocation hook در JwtEncoder.
     * توکن stateless بدون رکورد (encode مستقیم) با ساختار معتبر پذیرفته می‌شود
     * (رفتار استاندارد JWT)؛ ابطال فقط برای توکن‌های manager-issued معنا دارد.
     */
    public function isKnownRevoked(string $jti): bool
    {
        if ($jti === '') {
            return false;
        }

        $record = $this->db->table($this->table)->where('jti', $jti)->first();

        return $record !== null && (int) $record['revoked'] === 1;
    }

    /** پاک‌سازی توکن‌های منقضی (GC). */
    public function prune(): int
    {
        return $this->db->table($this->table)
            ->where('expires_at', '<', Clock::timestamp())
            ->delete();
    }

    private function makeAuthenticatable(string $subject): Authenticatable
    {
        return new GenericUser(['id' => $subject]);
    }
}

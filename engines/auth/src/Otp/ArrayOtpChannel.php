<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Otp;

use Tavaan\Core\Contracts\Auth\Authenticatable;

/**
 * تَوان | Tavaan — Auth
 * ArrayOtpChannel — Fake تست: ضبط کدهای ارسالی (DOC-014 §4).
 *
 * @api internal
 * @see docs/03-engines/auth.md
 */
final class ArrayOtpChannel implements OtpChannelInterface
{
    /** @var list<array{identifier: string, code: string, channel: string}> */
    private array $sent = [];

    public function send(Authenticatable $user, #[\SensitiveParameter] string $code, string $channel): bool
    {
        $this->sent[] = [
            'identifier' => (string) $user->getAuthIdentifier(),
            'code' => $code,
            'channel' => $channel,
        ];

        return true;
    }

    /** @return list<array{identifier: string, code: string, channel: string}> */
    public function sent(): array
    {
        return $this->sent;
    }

    public function lastCode(): ?string
    {
        $last = $this->sent[\count($this->sent) - 1] ?? null;

        return $last['code'] ?? null;
    }

    public function flush(): void
    {
        $this->sent = [];
    }
}

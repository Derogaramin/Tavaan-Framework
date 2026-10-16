<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Otp;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Log\LoggerInterface;

/**
 * تَوان | Tavaan — Auth
 * LogOtpChannel — کانال پیش‌فرض توسعه: ثبت OTP در لاگ (dev فقط!).
 *
 * ⚠️ در production باید کانال واقعی (sms/mail — M5) ثبت شود.
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class LogOtpChannel implements OtpChannelInterface
{
    public function __construct(private readonly ?LoggerInterface $logger = null)
    {
    }

    public function send(Authenticatable $user, #[\SensitiveParameter] string $code, string $channel): bool
    {
        $this->logger?->info('auth.otp.sent', [
            'identifier' => (string) $user->getAuthIdentifier(),
            'channel' => $channel,
            'code' => $code,   // ⚠️ فقط کانال dev — در prod لاگ نشود
        ]);

        return true;
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Otp;

use Tavaan\Core\Contracts\Auth\Authenticatable;

/**
 * تَوان | Tavaan — Auth
 * OtpChannelInterface — کانال ارسال OTP (sms/mail/… — موتور notification در M5).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
interface OtpChannelInterface
{
    /**
     * @return bool ارسال موفق بود؟
     */
    public function send(Authenticatable $user, #[\SensitiveParameter] string $code, string $channel): bool;
}

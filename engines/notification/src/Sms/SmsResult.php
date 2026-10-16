<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Sms;

/**
 * تَوان | Tavaan — Notification
 * SmsResult — نتیجهٔ ارسال پیامک.
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class SmsResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $messageId = null,
        public readonly string $error = '',
        public readonly int $cost = 0,
    ) {
    }

    public static function ok(string $messageId = '', int $cost = 0): self
    {
        return new self(true, $messageId !== '' ? $messageId : null, '', $cost);
    }

    public static function fail(string $error): self
    {
        return new self(false, null, $error);
    }
}

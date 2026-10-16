<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Sms;

use Tavaan\Engine\Notification\Messages\SmsMessage;

/**
 * تَوان | Tavaan — Contracts (Notification)
 * SmsGatewayInterface — قرارداد درگاه پیامک (DOC-066 §5).
 *
 * درایورها: Kavenegar, Melipayamak, Ghasedak, SMS.ir, FarazSMS, Log.
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
interface SmsGatewayInterface
{
    public function send(SmsMessage $message): SmsResult;

    public function status(string $messageId): DeliveryStatus;

    /** ماندهٔ اعتبار (ریال/واحد سرویس). */
    public function credit(): int;

    /** نام درایور (kavenegar/…). */
    public function name(): string;
}

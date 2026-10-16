<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification;

use Tavaan\Core\Contracts\Notification\Notifiable;

/**
 * تَوان | Tavaan — Notification
 * Notification — پایهٔ آگاه‌سازی‌های کاربر (DOC-066 §3).
 *
 * ```php
 * final class OrderShipped extends Notification
 * {
 *     public function __construct(public Order $order) {}
 *
 *     public function via(Notifiable $notifiable): array
 *     {
 *         return ['mail', 'sms', 'database'];
 *     }
 *     public function toMail(Notifiable $n): MailMessage { … }
 *     public function toSms(Notifiable $n): SmsMessage { … }
 *     public function toDatabase(Notifiable $n): array { … }
 * }
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
abstract class Notification
{
    /** شناسهٔ نمونه (برای ثبت در database channel). */
    public readonly string $id;

    public function __construct()
    {
        $this->id = (string) \Tavaan\Support\Ulid::generate();
    }

    /**
     * کانال‌های ارسال برای این گیرنده.
     *
     * @return list<string> مثل ['mail', 'sms', 'database']
     */
    abstract public function via(Notifiable $notifiable): array;

    // ─── سازندهٔ پیام هر کانال (override توسط کاربر) ─────────────────────

    /** @return \Tavaan\Engine\Notification\Messages\MailMessage|null */
    public function toMail(Notifiable $notifiable): ?object
    {
        return null;
    }

    /** @return \Tavaan\Engine\Notification\Messages\SmsMessage|null */
    public function toSms(Notifiable $notifiable): ?object
    {
        return null;
    }

    /** @return array<string, mixed>|null دادهٔ In-App */
    public function toDatabase(Notifiable $notifiable): ?array
    {
        return null;
    }

    /** @return \Tavaan\Engine\Notification\Messages\TelegramMessage|null */
    public function toTelegram(Notifiable $notifiable): ?object
    {
        return null;
    }

    /** کانال‌های bale/eitaa همان پیام telegram را مصرف می‌کنند. */
    public function toBale(Notifiable $notifiable): ?object
    {
        return $this->toTelegram($notifiable);
    }

    public function toEitaa(Notifiable $notifiable): ?object
    {
        return $this->toTelegram($notifiable);
    }

    /** @return \Tavaan\Engine\Notification\Messages\WebhookMessage|null */
    public function toWebhook(Notifiable $notifiable): ?object
    {
        return null;
    }

    /** @return object|array<string, mixed>|null رویداد/پیام realtime (موتور realtime در M6) */
    public function toBroadcast(Notifiable $notifiable): object|array|null
    {
        return null;
    }

    /** @return array<string, mixed>|null پیام push (FCM/APNs adapter) */
    public function toPush(Notifiable $notifiable): ?array
    {
        return null;
    }
}

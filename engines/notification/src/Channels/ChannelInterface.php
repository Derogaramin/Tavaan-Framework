<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Channels;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\Notification\Notification;

/**
 * تَوان | Tavaan — Notification
 * ChannelInterface — قرارداد کانال آگاه‌سازی (DOC-066 §2).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
interface ChannelInterface
{
    /**
     * ارسال پیام به گیرنده.
     *
     * @param mixed $message خروجی to{Channel}()
     *
     * @return mixed رسید/نتیجهٔ ارسال
     */
    public function send(Notifiable $notifiable, mixed $message, Notification $notification): mixed;

    /** نام کانال (mail/sms/database/…). */
    public function name(): string;
}

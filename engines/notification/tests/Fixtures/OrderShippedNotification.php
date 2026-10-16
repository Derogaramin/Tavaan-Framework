<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Tests\Fixtures;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\Notification\Messages\MailMessage;
use Tavaan\Engine\Notification\Messages\SmsMessage;
use Tavaan\Engine\Notification\Notification;

/** Fixture: آگاه‌سازی چندکاناله (DOC-066 §3). */
final class OrderShippedNotification extends Notification
{
    public function __construct(public readonly int $orderId = 101, public readonly string $trackingCode = 'TRK-1')
    {
        parent::__construct();
    }

    /** @return list<string> */
    public function via(Notifiable $notifiable): array
    {
        return ['mail', 'sms', 'database'];
    }

    public function toMail(Notifiable $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('سفارش ارسال شد')
            ->greeting('سلام!')
            ->line('سفارش #' . $this->orderId . ' ارسال شد.')
            ->action('مشاهدهٔ سفارش', 'https://shop.test/orders/' . $this->orderId);
    }

    public function toSms(Notifiable $notifiable): SmsMessage
    {
        return SmsMessage::create('سفارش شما ارسال شد. کد رهگیری: ' . $this->trackingCode);
    }

    /** @return array<string, mixed> */
    public function toDatabase(Notifiable $notifiable): array
    {
        return ['order_id' => $this->orderId, 'message' => 'ارسال شد'];
    }
}

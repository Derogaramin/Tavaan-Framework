<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Tests\Fixtures;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Core\Contracts\Queue\ShouldQueue;
use Tavaan\Engine\Notification\Messages\SmsMessage;
use Tavaan\Engine\Notification\Notification;

/** Fixture: آگاه‌سازی صف‌روند (T-04). */
final class QueuedTestNotification extends Notification implements ShouldQueue
{
    public function __construct(public readonly string $code = 'Q-1')
    {
        parent::__construct();
    }

    /** @return list<string> */
    public function via(Notifiable $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(Notifiable $notifiable): SmsMessage
    {
        return SmsMessage::create('کد تأیید صف: ' . $this->code);
    }
}

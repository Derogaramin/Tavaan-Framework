<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Tests\Fixtures;

use Tavaan\Engine\Notification\Messages\SmsMessage;
use Tavaan\Engine\Notification\Sms\DeliveryStatus;
use Tavaan\Engine\Notification\Sms\SmsGatewayInterface;
use Tavaan\Engine\Notification\Sms\SmsResult;

/** Fixture: درگاه پیامک ساختگی (T-05). */
final class MockSmsGateway implements SmsGatewayInterface
{
    /** @var list<SmsMessage> */
    public array $sent = [];

    public bool $shouldFail = false;

    public function send(SmsMessage $message): SmsResult
    {
        if ($this->shouldFail) {
            return SmsResult::fail('gateway down');
        }

        $this->sent[] = $message;

        return SmsResult::ok('mock-' . \count($this->sent));
    }

    public function status(string $messageId): DeliveryStatus
    {
        return DeliveryStatus::Delivered;
    }

    public function credit(): int
    {
        return 1000;
    }

    public function name(): string
    {
        return 'mock';
    }
}

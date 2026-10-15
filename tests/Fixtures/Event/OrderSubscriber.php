<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Events\EventSubscriber;

/** Fixture: Subscriber با چند رویداد. */
final class OrderSubscriber implements EventSubscriber
{
    public function subscribe(DispatcherInterface $events): array
    {
        return [
            OrderPaid::class => 'onPaid',
            'shop.order.canceled' => 'onCanceled',
        ];
    }

    public function onPaid(OrderPaid $event): void
    {
        ListenerRecorder::record('subscriber:paid:' . $event->orderId);
    }

    public function onCanceled(string $event, mixed $payload): void
    {
        ListenerRecorder::record('subscriber:canceled');
    }
}

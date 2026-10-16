<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Jobs;

use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Queue\JobInterface;
use Tavaan\Engine\Event\EventDispatcher;

/**
 * تَوان | Tavaan — Queue
 * CallQueuedEvent — wrapper داخلی اجرای رویدادهای صف‌روند (ShouldQueue).
 *
 * EventDispatcher رویدادهای `Contracts\Events\ShouldQueue` را به صف push می‌کند؛
 * این wrapper در Worker همان رویداد را بازسازی و با `dispatchNow()` منتشر
 * می‌کند — 🔒 بدون push مجدد به صف (جلوگیری از حلقهٔ بی‌پایان).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class CallQueuedEvent
{
    public function __construct(private readonly ?\Tavaan\Engine\Queue\QueueManager $manager = null)
    {
    }

    /**
     * @param array<string, mixed> $data {eventName, event, payload}
     */
    public function handle(JobInterface $job, array $data = []): void
    {
        $manager = $this->manager
            ?? throw new \RuntimeException('CallQueuedEvent requires QueueManager.');

        $container = $manager->container();

        $dispatcher = $container->bound('events') ? $container->make('events') : null;
        if (!$dispatcher instanceof DispatcherInterface) {
            throw new \RuntimeException('CallQueuedEvent requires the event dispatcher.');
        }

        $eventName = (string) ($data['eventName'] ?? '');
        if ($eventName === '') {
            return;
        }

        $serializer = $manager->serializer();
        $eventProps = $data['event'] ?? [];
        $event = \is_array($eventProps) && $eventProps !== []
            ? $serializer->restoreCommand(['eventName' => $eventName, 'command' => $eventProps])
            : $container->make($eventName);

        $payload = $serializer->restoreEventPayload($data);

        // 🔒 dispatchNow: marker ShouldQueue نادیده گرفته می‌شود (بدون requeue)
        if ($dispatcher instanceof EventDispatcher) {
            $dispatcher->dispatchNow($event, $payload);

            return;
        }

        $dispatcher->dispatch($event, $payload);
    }

    /**
     * شکست نهایی — رویداد صف‌روند hook کاربری ندارد؛ فقط Batch (در صورت وجود).
     *
     * @param array<string, mixed> $payload
     */
    public function failed(array $payload, \Throwable $e): void
    {
        // رویدادها Batch ندارند و hook کاربری نمی‌گیرند؛ Worker لاگ و
        // failed_jobs را ثبت می‌کند — اینجا عمدتاً no-op است.
    }
}

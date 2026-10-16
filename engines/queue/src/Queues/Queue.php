<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Queues;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\ShouldQueue as EventShouldQueue;
use Tavaan\Core\Contracts\Queue\QueueInterface;
use Tavaan\Engine\Queue\Serializer;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Queue
 * Queue — پایهٔ اتصالات صف: ساخت payload، نام صف، زمان‌دسترس (DOC-061 §2/§7).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
abstract class Queue implements QueueInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected readonly string $connectionName,
        protected readonly array $config,
        protected readonly Serializer $serializer,
        protected readonly ?ContainerInterface $container = null,
    ) {
    }

    public function push(object|string $job, array $data = [], ?string $queue = null): int|string|null
    {
        $queueName = $this->resolveQueueName($job, $queue);
        $payload = $this->createPayload($job, $data, $queueName);

        // delay روی شیء job (trait Queueable) → later
        $delay = \is_object($job) && \property_exists($job, 'delay') ? (int) ($job->delay ?? 0) : 0;
        if ($delay > 0) {
            return $this->laterRaw(Clock::timestamp() + $delay, $payload, $queueName);
        }

        return $this->pushRaw($payload, $queueName);
    }

    public function pushOn(string $queue, object|string $job, array $data = []): int|string|null
    {
        return $this->push($job, $data, $queue);
    }

    public function later(\DateTimeInterface|int $delay, object|string $job, array $data = [], ?string $queue = null): int|string|null
    {
        $queueName = $this->resolveQueueName($job, $queue);
        $payload = $this->createPayload($job, $data, $queueName);

        return $this->laterRaw($this->availableAt($delay), $payload, $queueName);
    }

    public function laterOn(string $queue, \DateTimeInterface|int $delay, object|string $job, array $data = []): int|string|null
    {
        return $this->later($delay, $job, $data, $queue);
    }

    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    // ─── عملیات backend (هر درایور پیاده می‌کند) ─────────────────────────

    /** push یک payload آمادهٔ JSON (برای Chain/Batch). */
    abstract public function pushRaw(string $payload, ?string $queue = null): int|string|null;

    /** push با زمان‌دسترس (unix timestamp). */
    abstract public function laterRaw(int $availableAt, string $payload, ?string $queue = null): int|string|null;

    // ─── داخلی ────────────────────────────────────────────────────────────

    protected function createPayload(object|string $job, array $data, string $queue): string
    {
        // رویداد ShouldQueue → wrapper رویداد (اجرا با dispatchNow در Worker)
        if (\is_object($job) && $job instanceof EventShouldQueue) {
            return $this->serializer->createEventPayload($job, $data, $queue);
        }

        return $this->serializer->createJobPayload($job, $data, $queue);
    }

    protected function resolveQueueName(object|string $job, ?string $queue): string
    {
        if (\is_string($queue) && $queue !== '') {
            return $queue;
        }

        if (\is_object($job) && \property_exists($job, 'queue')) {
            $name = $job->queue;
            if (\is_string($name) && $name !== '') {
                return $name;
            }
        }

        $configured = $this->config['queue'] ?? 'default';

        return \is_string($configured) && $configured !== '' ? $configured : 'default';
    }

    protected function availableAt(\DateTimeInterface|int $delay): int
    {
        return $delay instanceof \DateTimeInterface
            ? $delay->getTimestamp()
            : Clock::timestamp() + \max(0, $delay);
    }

    protected function defaultQueue(): string
    {
        $configured = $this->config['queue'] ?? 'default';

        return \is_string($configured) && $configured !== '' ? $configured : 'default';
    }
}

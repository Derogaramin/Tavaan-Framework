<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * TaskBroadcaster — realtime/SSE رویدادهای کار (DOC-098 §8).
 */

namespace Modules\Bpms\Infrastructure\Realtime;

use Tavaan\Core\Facade\Facades\Broadcast;

/**
 * TaskBroadcaster — شنوندهٔ `bpms.task.created`/`bpms.task.completed`/
 * `bpms.process.completed`:
 *
 * - کانال `bpms` (سراسری — داشبورد پایش زنده)
 * - کانال `bpms.instance.{ULID}` (صفحهٔ نمونه)
 *
 * مشترکین SSE موتور realtime از راه `GET /events/stream?channels=bpms`
 * رویدادها را بی‌درنگ می‌گیرند (بدون ext — الگوی forum).
 * best-effort: خطای realtime جریان را نمی‌شکند.
 *
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class TaskBroadcaster
{
    /** @param array<string, mixed> $config کلید config('bpms.realtime') */
    public function __construct(private readonly array $config = [])
    {
    }

    /** @param mixed $payload */
    public function onTaskCreated(string $event, $payload): void
    {
        $this->publishEvent($event, (array) $payload, 'task.created');
    }

    /** @param mixed $payload */
    public function onTaskCompleted(string $event, $payload): void
    {
        $this->publishEvent($event, (array) $payload, 'task.completed');
    }

    /** @param mixed $payload */
    public function onProcessCompleted(string $event, $payload): void
    {
        $this->publishEvent($event, (array) $payload, 'process.completed');
    }

    /** @param mixed $payload */
    public function onTaskEscalated(string $event, $payload): void
    {
        $this->publishEvent($event, (array) $payload, 'task.escalated');
    }

    /** @param array<string, mixed> $payload */
    private function publishEvent(string $event, array $payload, string $name): void
    {
        if (!$this->enabled()) {
            return;
        }

        // 🔒 متغیرهای فرایند هرگز broadcast نمی‌شوند (نشت داده — §8)
        $lite = [];
        foreach (['task_id', 'instance_id', 'state_id', 'title', 'definition_key', 'business_key'] as $key) {
            if (isset($payload[$key]) && \is_scalar($payload[$key])) {
                $lite[$key] = $payload[$key];
            }
        }

        $this->publish($this->globalChannel(), $name, $lite);

        $instanceId = (string) ($payload['instance_id'] ?? '');
        if ($instanceId !== '') {
            $this->publish($this->instanceChannel($instanceId), $name, $lite);
        }
    }

    /** @param array<string, mixed> $payload */
    private function publish(string $channel, string $name, array $payload): void
    {
        if ($channel === '') {
            return;
        }

        try {
            Broadcast::publish($channel, $name, $payload);
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    private function globalChannel(): string
    {
        return (string) ($this->config['channel'] ?? 'bpms');
    }

    private function instanceChannel(string $instanceId): string
    {
        return (string) ($this->config['instance_channel_prefix'] ?? 'bpms.instance.') . $instanceId;
    }
}

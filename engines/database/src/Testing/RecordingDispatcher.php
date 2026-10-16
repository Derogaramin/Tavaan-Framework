<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Testing;

use Tavaan\Core\Contracts\Events\DispatcherInterface;

/**
 * تَوان | Tavaan — Database
 * RecordingDispatcher — Fake ضبط رویدادها برای تست (DOC-014 §4 / قاعده C7).
 *
 * @api internal
 * @see docs/07-quality/testing-strategy.md
 */
final class RecordingDispatcher implements DispatcherInterface
{
    /** @var list<array{event: string, payload: mixed}> */
    private array $recorded = [];

    public function listen(string|array $events, callable|string|array $listener, int $priority = 0): void
    {
    }

    public function subscribe(string|object $subscriber): void
    {
    }

    public function hasListeners(string $event): bool
    {
        return false;
    }

    public function dispatch(object|string $event, mixed $payload = [], bool $halt = false): mixed
    {
        $name = \is_object($event) ? $event::class : $event;
        $this->recorded[] = ['event' => $name, 'payload' => $payload];

        return [];
    }

    public function until(string|object $event, mixed $payload = []): mixed
    {
        return $this->dispatch($event, $payload, true);
    }

    public function forget(string $event): void
    {
    }

    public function push(string $event, mixed $payload = []): void
    {
        $this->dispatch($event, $payload);
    }

    /** @return list<array{event: string, payload: mixed}> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function hasRecorded(string $event): bool
    {
        foreach ($this->recorded as $r) {
            if ($r['event'] === $event) {
                return true;
            }
        }

        return false;
    }

    /** @return list<mixed> */
    public function payloadsFor(string $event): array
    {
        return \array_values(\array_map(
            static fn (array $r): mixed => $r['payload'],
            \array_filter($this->recorded, static fn (array $r): bool => $r['event'] === $event),
        ));
    }

    public function flush(): void
    {
        $this->recorded = [];
    }
}

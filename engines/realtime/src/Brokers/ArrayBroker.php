<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime\Brokers;

use Tavaan\Core\Contracts\Realtime\BrokerInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Realtime
 * ArrayBroker — broker در حافظه (تست/تک‌فرایند).
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class ArrayBroker implements BrokerInterface
{
    /** @var list<array{id: int, channel: string, name: string, payload: array<string, mixed>, at: string}> */
    private array $events = [];

    private int $seq = 0;

    private int $cap;

    public function __construct(int $cap = 1000)
    {
        $this->cap = \max(1, $cap);
    }

    public function publish(string $channel, string $name, array $payload): void
    {
        $this->events[] = [
            'id' => ++$this->seq,
            'channel' => $channel,
            'name' => $name,
            'payload' => $payload,
            'at' => Clock::isoNow(),
        ];
        if (\count($this->events) > $this->cap) {
            $this->events = \array_slice($this->events, -$this->cap);
        }
    }

    public function poll(array $channels, int $timeout = 1, int $afterId = 0): array
    {
        $set = \array_flip($channels);
        $out = [];
        foreach ($this->events as $event) {
            if ($event['id'] > $afterId && isset($set[$event['channel']])) {
                $out[] = $event;
            }
        }

        return $out;
    }

    public function lastId(): int
    {
        return $this->seq;
    }

    public function name(): string
    {
        return 'array';
    }

    public function clear(): void
    {
        $this->events = [];
        $this->seq = 0;
    }
}

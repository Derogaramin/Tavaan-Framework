<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime\Tests\Fixtures;

use Tavaan\Core\Contracts\Realtime\ShouldBroadcast;
use Tavaan\Engine\Queue\Redis\RedisClientInterface;

/**
 * Fixtureهای تست Realtime.
 *
 * @api internal
 */

/** رویداد ShouldBroadcast (DOC-045 §3). */
final class OrderStatusChanged implements ShouldBroadcast
{
    public bool $broadcast = true;

    public function __construct(
        public readonly int $orderId,
        public readonly string $status,
    ) {
    }

    public function broadcastOn(): array
    {
        return ['orders.' . $this->orderId];
    }

    public function broadcastAs(): string
    {
        return 'order.status';
    }

    public function broadcastWith(): array
    {
        return ['status' => $this->status, 'order_id' => $this->orderId];
    }

    public function broadcastWhen(): bool
    {
        return $this->broadcast;
    }
}

/** کاربر ساختگی برای احراز کانال. */
final class RUser
{
    /** @param list<string> $abilities */
    public function __construct(
        public readonly int $id,
        public readonly string $name = 'user',
        public readonly array $abilities = [],
    ) {
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    public function can(string $ability): bool
    {
        return \in_array($ability, $this->abilities, true);
    }
}

/**
 * Redis ساختگی با پشتیبانی زیرمجموعهٔ دستورات RedisBroker.
 */
final class FakeRealtimeRedis implements RedisClientInterface
{
    /** @var array<string, list<string>> */
    public array $lists = [];

    /** @var array<string, int> */
    public array $ints = [];

    public function command(string $command, mixed ...$args): mixed
    {
        return match (\strtoupper($command)) {
            'INCR' => $this->ints[(string) $args[0]] = ($this->ints[(string) $args[0]] ?? 0) + 1,
            'GET' => isset($this->ints[(string) $args[0]]) ? (string) $this->ints[(string) $args[0]] : null,
            'RPUSH' => (function () use ($args): int {
                $key = (string) $args[0];
                $this->lists[$key] ??= [];
                foreach (\array_slice($args, 1) as $value) {
                    $this->lists[$key][] = (string) $value;
                }

                return \count($this->lists[$key]);
            })(),
            'LTRIM' => (function () use ($args): string {
                $key = (string) $args[0];
                $start = (int) $args[1];
                $stop = (int) $args[2];
                $list = $this->lists[$key] ?? [];
                $len = \count($list);
                if ($start < 0) {
                    $start = \max(0, $len + $start);
                }
                if ($stop < 0) {
                    $stop = $len + $stop;
                }
                $this->lists[$key] = \array_slice($list, $start, $stop - $start + 1);

                return 'OK';
            })(),
            'LRANGE' => (function () use ($args): array {
                $key = (string) $args[0];
                $start = (int) $args[1];
                $stop = (int) $args[2];
                $list = $this->lists[$key] ?? [];
                $len = \count($list);
                if ($start < 0) {
                    $start = \max(0, $len + $start);
                }
                if ($stop < 0) {
                    $stop = $len + $stop;
                }

                return \array_slice($list, $start, $stop - $start + 1);
            })(),
            default => null,
        };
    }

    public function ping(): bool
    {
        return true;
    }

    public function close(): void
    {
    }

    public function prefix(): string
    {
        return 'tvn:';
    }
}

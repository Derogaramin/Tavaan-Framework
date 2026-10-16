<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime\Brokers;

use Tavaan\Core\Contracts\Realtime\BrokerInterface;
use Tavaan\Engine\Queue\Redis\RedisClientInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Realtime
 * RedisBroker — broker روی Redis (بازاستفاده از RedisClient موتور Queue).
 *
 * مدل داده: به‌جای Pub/Sub (که اتصال blocking می‌خواهد)، هر کانال یک
 * LIST با سقف است و رویدادها `seq` سراسری دارند (INCR) — مصرف‌کننده
 * با afterId خودش fan-out می‌خواند. این با مدل poll در SSE/WS سازگار است.
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class RedisBroker implements BrokerInterface
{
    private const SEQ_KEY = 'tvn:rt:seq';

    public function __construct(
        private readonly RedisClientInterface $redis,
        private readonly int $cap = 1000,
        private readonly int $maxPerPoll = 200,
    ) {
    }

    public function publish(string $channel, string $name, array $payload): void
    {
        $seq = (int) $this->redis->command('INCR', self::SEQ_KEY);
        $entry = (string) \json_encode([
            'id' => $seq,
            'channel' => $channel,
            'name' => $name,
            'payload' => $payload,
            'at' => Clock::isoNow(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        $key = $this->channelKey($channel);
        $this->redis->command('RPUSH', $key, $entry);
        $this->redis->command('LTRIM', $key, -$this->cap, -1);
    }

    public function poll(array $channels, int $timeout = 1, int $afterId = 0): array
    {
        $deadline = \microtime(true) + \max(0, $timeout);
        $out = [];

        do {
            foreach ($channels as $channel) {
                /** @var list<string>|null $entries */
                $entries = $this->redis->command('LRANGE', $this->channelKey($channel), -500, -1);
                foreach ((array) $entries as $raw) {
                    $event = \json_decode((string) $raw, true);
                    if (\is_array($event) && (int) ($event['id'] ?? 0) > $afterId) {
                        $out[] = $event;
                    }
                }
            }

            if ($out !== [] || \microtime(true) >= $deadline) {
                break;
            }
            \usleep(50_000);
        } while (true);

        \usort($out, static fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);

        return \array_slice($out, 0, $this->maxPerPoll);
    }

    public function lastId(): int
    {
        $value = $this->redis->command('GET', self::SEQ_KEY);

        return $value === null || $value === false ? 0 : (int) $value;
    }

    public function name(): string
    {
        return 'redis';
    }

    private function channelKey(string $channel): string
    {
        return 'tvn:rt:ch:' . $channel;
    }
}

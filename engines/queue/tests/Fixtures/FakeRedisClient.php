<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Fixtures;

use Tavaan\Engine\Queue\Queues\RedisQueue;
use Tavaan\Engine\Queue\Redis\RedisClientInterface;

/**
 * Fixture: کلاینت Redis درون‌حافظه‌ای برای تست درایور redis بدون سرور.
 *
 * دستورات پشتیبانی‌شده: RPUSH/LPOP/LLEN/ZADD/ZREM/ZCARD/PING/DEL
 * و EVAL برای دو اسکریپت POP_SCRIPT/RELEASE_SCRIPT موتور Queue
 * (منطق Lua به‌شکل معادل PHP بازتولید می‌شود).
 */
final class FakeRedisClient implements RedisClientInterface
{
    /** @var array<string, list<string>> */
    private array $lists = [];

    /** @var array<string, array<string, int>> key → member → score */
    private array $zsets = [];

    public function __construct(private readonly string $prefix = 'tvn:')
    {
    }

    public function command(string $command, mixed ...$args): mixed
    {
        return match (\strtoupper($command)) {
            'PING' => 'PONG',
            'RPUSH' => $this->rpush((string) $args[0], ...$this->strings(\array_slice($args, 1))),
            'LPOP' => $this->lpop((string) $args[0]),
            'LLEN' => \count($this->lists[(string) $args[0]] ?? []),
            'ZADD' => $this->zadd((string) $args[0], (int) $args[1], (string) $args[2]),
            'ZREM' => $this->zrem((string) $args[0], (string) $args[1]),
            'ZCARD' => \count($this->zsets[(string) $args[0]] ?? []),
            'DEL' => $this->del(...$this->strings($args)),
            'EVAL' => $this->evalScript((string) $args[0], \array_slice($args, 1)),
            default => throw new \RuntimeException('FakeRedisClient: دستور پشتیبانی‌نشده ' . $command),
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
        return $this->prefix;
    }

    // ─── پیاده‌سازی دستورات ───────────────────────────────────────────────

    private function rpush(string $key, string ...$values): int
    {
        foreach ($values as $value) {
            $this->lists[$key][] = $value;
        }

        return \count($this->lists[$key]);
    }

    private function lpop(string $key): string|false|null
    {
        if (($this->lists[$key] ?? []) === []) {
            return null;
        }

        return \array_shift($this->lists[$key]);
    }

    private function zadd(string $key, int $score, string $member): int
    {
        $isNew = !isset($this->zsets[$key][$member]);
        $this->zsets[$key][$member] = $score;

        return $isNew ? 1 : 0;
    }

    private function zrem(string $key, string $member): int
    {
        if (!isset($this->zsets[$key][$member])) {
            return 0;
        }
        unset($this->zsets[$key][$member]);

        return 1;
    }

    private function del(string ...$keys): int
    {
        $n = 0;
        foreach ($keys as $key) {
            $n += (isset($this->lists[$key]) ? 1 : 0) + (isset($this->zsets[$key]) ? 1 : 0);
            unset($this->lists[$key], $this->zsets[$key]);
        }

        return $n;
    }

    /** @param list<mixed> $rest [numkeys, ...keys, ...argv] */
    private function evalScript(string $script, array $rest): mixed
    {
        $numKeys = (int) ($rest[0] ?? 0);
        $keys = \array_map(strval(...), \array_slice($rest, 1, $numKeys));
        $argv = \array_slice($rest, 1 + $numKeys);

        if ($script === RedisQueue::POP_SCRIPT) {
            return $this->popScript($keys[0], $keys[1], $keys[2], (int) $argv[0], (int) $argv[1]);
        }

        if ($script === RedisQueue::RELEASE_SCRIPT) {
            $this->zrem($keys[0], (string) $argv[0]);
            $this->zadd($keys[1], (int) $argv[1], (string) $argv[0]);

            return 1;
        }

        throw new \RuntimeException('FakeRedisClient: اسکریپت ناشناخته');
    }

    /** معادل PHP اسکریپت Lua pop (همان ترتیب عملیات). */
    private function popScript(string $delayed, string $ready, string $reserved, int $now, int $retryAfter): string|false
    {
        foreach ($this->zsets[$delayed] ?? [] as $member => $score) {
            if ($score <= $now) {
                $this->zrem($delayed, (string) $member);
                $this->rpush($ready, (string) $member);
            }
        }

        foreach ($this->zsets[$reserved] ?? [] as $member => $score) {
            if ($score <= $now - $retryAfter) {
                $this->zrem($reserved, (string) $member);
                $this->rpush($ready, (string) $member);
            }
        }

        $payload = $this->lpop($ready);
        if (!\is_string($payload)) {
            return false;   // nil در RESP
        }

        // معادل cjson: attempts داخل payload افزایش می‌یابد
        $decoded = \json_decode($payload, true);
        if (\is_array($decoded)) {
            $decoded['attempts'] = ((int) ($decoded['attempts'] ?? 0)) + 1;
            $payload = (string) \json_encode($decoded, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        $this->zadd($reserved, $now, $payload);

        return $payload;
    }

    /** @param list<mixed> $values @return list<string> */
    private function strings(array $values): array
    {
        return \array_values(\array_map(strval(...), $values));
    }
}

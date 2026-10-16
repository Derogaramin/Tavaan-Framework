<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Queues;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Queue\JobInterface;
use Tavaan\Engine\Queue\Jobs\RedisJob;
use Tavaan\Engine\Queue\Redis\RedisClient;
use Tavaan\Engine\Queue\Redis\RedisClientInterface;
use Tavaan\Engine\Queue\Serializer;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Queue
 * RedisQueue — درایور redis با ساختار LIST (ready) + ZSET (delayed/reserved).
 *
 * 🔒 Pop اتمیک با Lua script (EVAL): مهاجرت delayedهای سررسید و
 * reservedهای گیرکرده، سپس LPOP + ثبت در reserved — یک عملیات اتمیک.
 * attempts داخل payload (با cjson) افزایش می‌یابد.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class RedisQueue extends Queue
{
    /**
     * اسکریپت pop اتمیک — KEYS: [delayed, ready, reserved]؛ ARGV: [now, retryAfter].
     */
    public const POP_SCRIPT = <<<'LUA'
        local delayed = KEYS[1]
        local ready = KEYS[2]
        local reserved = KEYS[3]
        local now = tonumber(ARGV[1])
        local retryAfter = tonumber(ARGV[2])
        local due = redis.call('zrangebyscore', delayed, '-inf', now, 'limit', 0, 100)
        for i, payload in ipairs(due) do
            redis.call('zrem', delayed, payload)
            redis.call('rpush', ready, payload)
        end
        local expired = redis.call('zrangebyscore', reserved, '-inf', now - retryAfter, 'limit', 0, 100)
        for i, payload in ipairs(expired) do
            redis.call('zrem', reserved, payload)
            redis.call('rpush', ready, payload)
        end
        local payload = redis.call('lpop', ready)
        if not payload then
            return false
        end
        local decoded = cjson.decode(payload)
        decoded['attempts'] = (decoded['attempts'] or 0) + 1
        payload = cjson.encode(decoded)
        redis.call('zadd', reserved, now, payload)
        return payload
        LUA;

    /**
     * اسکریپس release — KEYS: [reserved, delayed]؛ ARGV: [payload, score].
     */
    public const RELEASE_SCRIPT = <<<'LUA'
        redis.call('zrem', KEYS[1], ARGV[1])
        redis.call('zadd', KEYS[2], tonumber(ARGV[2]), ARGV[1])
        return 1
        LUA;

    private readonly int $retryAfter;

    private ?RedisClientInterface $client = null;

    /** @param array<string, mixed> $config */
    public function __construct(
        string $connectionName,
        array $config,
        Serializer $serializer,
        ?ContainerInterface $container = null,
        ?RedisClientInterface $client = null,
    ) {
        parent::__construct($connectionName, $config, $serializer, $container);

        $this->retryAfter = (int) ($config['retry_after'] ?? 90);
        $this->client = $client;
    }

    /** کلاینت (تنبل) — برای تست قابل تزریق. */
    public function client(): RedisClientInterface
    {
        return $this->client ??= new RedisClient($this->config);
    }

    public function pushRaw(string $payload, ?string $queue = null): int|string|null
    {
        $queue ??= $this->queueFromPayload($payload);

        return $this->client()->command('RPUSH', $this->readyKey($queue), $payload) ?: null;
    }

    public function laterRaw(int $availableAt, string $payload, ?string $queue = null): int|string|null
    {
        $queue ??= $this->queueFromPayload($payload);
        $this->client()->command('ZADD', $this->delayedKey($queue), $availableAt, $payload);

        return null;
    }

    public function pop(?string $queue = null): ?JobInterface
    {
        $queue ??= $this->defaultQueue();
        $now = Clock::timestamp();

        /** @var string|false|null $payload */
        $payload = $this->client()->command(
            'EVAL',
            self::POP_SCRIPT,
            3,
            $this->delayedKey($queue),
            $this->readyKey($queue),
            $this->reservedKey($queue),
            $now,
            $this->retryAfter,
        );

        if (!\is_string($payload) || $payload === '') {
            return null;
        }

        $decoded = \json_decode($payload, true);
        $attempts = \is_array($decoded) ? (int) ($decoded['attempts'] ?? 1) : 1;

        return new RedisJob($this->container, $this->connectionName, $queue, $payload, $attempts, $this);
    }

    public function size(?string $queue = null): int
    {
        $queue ??= $this->defaultQueue();
        $ready = (int) $this->client()->command('LLEN', $this->readyKey($queue));
        $delayed = (int) $this->client()->command('ZCARD', $this->delayedKey($queue));

        return $ready + $delayed;
    }

    /** حذف job از reserved (پس از پردازش موفق/شکست نهایی). */
    public function deleteReserved(string $queue, string $payload): void
    {
        $this->client()->command('ZREM', $this->reservedKey($queue), $payload);
    }

    /** برگرداندن job به delayed با تأخیر. */
    public function releaseReserved(string $queue, string $payload, int $delay): void
    {
        $this->client()->command(
            'EVAL',
            self::RELEASE_SCRIPT,
            2,
            $this->reservedKey($queue),
            $this->delayedKey($queue),
            $payload,
            Clock::timestamp() + $delay,
        );
    }

    // ─── کلیدها ───────────────────────────────────────────────────────────

    private function readyKey(string $queue): string
    {
        return $this->client()->prefix() . 'queues:' . $queue;
    }

    private function delayedKey(string $queue): string
    {
        return $this->client()->prefix() . 'queues:' . $queue . ':delayed';
    }

    private function reservedKey(string $queue): string
    {
        return $this->client()->prefix() . 'queues:' . $queue . ':reserved';
    }

    private function queueFromPayload(string $payload): string
    {
        $decoded = \json_decode($payload, true);

        return \is_array($decoded) && \is_string($decoded['queue'] ?? null) && $decoded['queue'] !== ''
            ? $decoded['queue']
            : $this->defaultQueue();
    }
}

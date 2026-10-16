<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Redis;

/**
 * تَوان | Tavaan — Queue
 * RedisClientInterface — قرارداد کلاینت RESP (قابل Mock برای تست).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
interface RedisClientInterface
{
    /**
     * ارسال دستور Redis و خواندن پاسخ.
     *
     * @param list<mixed> $args
     */
    public function command(string $command, mixed ...$args): mixed;

    public function ping(): bool;

    public function close(): void;

    public function prefix(): string;
}

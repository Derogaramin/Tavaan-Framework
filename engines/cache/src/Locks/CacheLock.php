<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache\Locks;

use Tavaan\Core\Contracts\Cache\LockInterface;
use Tavaan\Core\Contracts\Cache\StoreInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Cache
 * CacheLock — قفل اتمیک مبتنی بر store (DOC-060 §4).
 *
 * 🔒 acquire = store->add (NX اتمیک در همهٔ درایورها).
 * 🔒 release فقط توسط مالک (owner token) — جلوگیری از آزادسازی قفل دیگران.
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class CacheLock implements LockInterface
{
    private bool $acquired = false;

    public function __construct(
        private readonly StoreInterface $store,
        private readonly string $name,
        private readonly int $seconds,
        private readonly string $ownerToken,
        private readonly int $retryMs = 100,
    ) {
    }

    public function acquire(?int $timeout = null): bool
    {
        $deadline = $timeout === null ? null : Clock::timestamp() + $timeout;

        do {
            if ($this->store->add($this->name, $this->ownerToken, \max(1, $this->seconds))) {
                $this->acquired = true;

                return true;
            }

            if ($deadline === null) {
                return false;
            }

            \usleep($this->retryMs * 1000);
        } while (Clock::timestamp() < $deadline);

        return false;
    }

    public function block(int $timeout, ?callable $callback = null): mixed
    {
        if (!$this->acquire($timeout)) {
            throw new \Tavaan\Engine\Cache\Exceptions\LockTimeoutException(name: $this->name);
        }

        if ($callback === null) {
            return true;
        }

        try {
            return $callback();
        } finally {
            $this->release();
        }
    }

    public function release(): bool
    {
        // 🔒 فقط اگر مالک مطابقت کند
        $current = $this->store->get($this->name);
        if (\is_string($current) && \hash_equals($current, $this->ownerToken)) {
            $this->store->forget($this->name);
        }

        $this->acquired = false;

        return true;
    }

    public function forceRelease(): bool
    {
        $this->store->forget($this->name);
        $this->acquired = false;

        return true;
    }

    public function owner(): string
    {
        return $this->ownerToken;
    }

    public function isAcquired(): bool
    {
        return $this->acquired;
    }

    public static function generateOwnerToken(): string
    {
        return \bin2hex(\random_bytes(16));
    }
}

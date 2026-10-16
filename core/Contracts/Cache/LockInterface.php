<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Cache;

/**
 * تَوان | Tavaan — Contracts
 * LockInterface — قرارداد قفل اتمیک (DOC-014 §2.5).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
interface LockInterface
{
    /**
     * تلاش برای تصاحب قفل.
     *
     * @param int|null $timeout ثانیه‌های انتظار (null = بدون انتظار)
     */
    public function acquire(?int $timeout = null): bool;

    /**
     * تصاحب با انتظار + اجرای callback.
     *
     * @param callable|null $callback
     */
    public function block(int $timeout, ?callable $callback = null): mixed;

    /** آزادسازی فقط اگر مالک مطابقت کند. */
    public function release(): bool;

    /** آزادسازی بدون بررسی مالک. */
    public function forceRelease(): bool;

    public function owner(): string;
}

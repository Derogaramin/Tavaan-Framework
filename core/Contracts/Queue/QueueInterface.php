<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Queue;

/**
 * تَوان | Tavaan — Contracts
 * QueueInterface — قرارداد اتصال صف (DOC-061 §3).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
interface QueueInterface
{
    /**
     * Push یک Job (شیء یا نام کلاس + داده).
     *
     * @param object|string        $job   شیء Job یا class-string
     * @param array<string, mixed> $data  آرگومان‌های سازنده (وقتی $job رشته است)
     *
     * @return int|string|null شناسهٔ job در صف
     */
    public function push(object|string $job, array $data = [], ?string $queue = null): int|string|null;

    /** Push روی صف مشخص. */
    public function pushOn(string $queue, object|string $job, array $data = []): int|string|null;

    /**
     * Push با تأخیر (Delayed Job).
     *
     * @param \DateTimeInterface|int $delay زمان اجرا یا ثانیه‌های تأخیر
     */
    public function later(\DateTimeInterface|int $delay, object|string $job, array $data = [], ?string $queue = null): int|string|null;

    /** Later روی صف مشخص. */
    public function laterOn(string $queue, \DateTimeInterface|int $delay, object|string $job, array $data = []): int|string|null;

    /**
     * Pop اتمیک یک job آماده (بدون حذف — تا delete/release).
     *
     * 🔒 در درایور database با FOR UPDATE SKIP LOCKED — workerهای
     * موازی هرگز یک job را دوبار پردازش نمی‌کنند (DOC-061 §6).
     */
    public function pop(?string $queue = null): ?JobInterface;

    /** تعداد jobهای در انتظار (آماده + delayed). */
    public function size(?string $queue = null): int;

    /** نام اتصال (connection) این صف. */
    public function getConnectionName(): ?string;
}

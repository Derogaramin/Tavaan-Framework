<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue;

/**
 * تَوان | Tavaan — Queue
 * Queueable — trait رفتارهای صف برای job (DOC-061 §3).
 *
 * ```php
 * final class SendInvoiceEmailJob implements ShouldQueue
 * {
 *     use Queueable;
 *     public int $tries = 3;         // 👈 توسط خود کلاس تعریف می‌شود
 *     public int $timeout = 60;
 *     public int $backoff = 10;
 *     public string $queue = 'mail';
 * }
 * ```
 *
 * 🔒 این trait عمداً `tries/timeout/backoff/queue/connection` را تعریف
 * **نمی‌کند** — طبق مثال DOC-061 §3 کلاس job آن‌ها را (با نوع دلخواه)
 * اعلام می‌کند؛ تعریف همزمان در trait باعث خطای ناسازگاری PHP می‌شد.
 * Serializer/Queue این metadata را با `property_exists` می‌خوانند.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
trait Queueable
{
    /** تأخیر پیش از اولین اجرا (ثانیه) */
    public int $delay = 0;

    /** @var array{key: string, max: int, seconds: int}|null محدودیت نرخ */
    public ?array $rateLimit = null;

    /** @var list<object|string> اعضای زنجیرهٔ پس از این job */
    public array $chained = [];

    /**
     * صف مقصد — کلاس job باید `$queue` را اعلام کند
     * (یا از قبل property عمومی داشته باشد).
     */
    public function onQueue(string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    /** اتصال مقصد — کلاس job باید `$connection` را اعلام کند. */
    public function onConnection(string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function withDelay(int|\DateTimeInterface $delay): static
    {
        $this->delay = $delay instanceof \DateTimeInterface
            ? \max(0, $delay->getTimestamp() - \time())
            : \max(0, $delay);

        return $this;
    }

    /** حداکثر تلاش‌ها — کلاس job باید `$tries` را اعلام کند. */
    public function withTries(int $tries): static
    {
        $this->tries = $tries;

        return $this;
    }

    /** محدودیت نرخ — مثلاً حداکثر ۱۰۰ اجرا در ۶۰ ثانیه (DOC-061 §3). */
    public function withRateLimit(string $key, int $max, int $seconds = 60): static
    {
        $this->rateLimit = ['key' => $key, 'max' => $max, 'seconds' => $seconds];

        return $this;
    }

    /**
     * زنجیره: پس از موفقیت این job، اعضا به‌ترتیب اجرا می‌شوند.
     *
     * @param iterable<object|string> $chain
     */
    public function chain(iterable $chain): static
    {
        foreach ($chain as $member) {
            $this->chained[] = $member;
        }

        return $this;
    }
}

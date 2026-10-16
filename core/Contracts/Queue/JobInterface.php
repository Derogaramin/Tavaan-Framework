<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Queue;

/**
 * تَوان | Tavaan — Contracts
 * JobInterface — قرارداد یک job برداشته‌شده از صف (DOC-061 §4).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
interface JobInterface
{
    /** اجرای job: resolve دستور از payload + فراخوانی handle (با timeout). */
    public function fire(): void;

    /** @return array<string, mixed> payload کامل (json-decoded) */
    public function payload(): array;

    /** شمارهٔ تلاش جاری (از ۱). */
    public function attempts(): int;

    /** حداکثر تلاش‌ها (null = نامحدود/پیش‌فرض worker). */
    public function maxTries(): ?int;

    /** timeout اجرا به ثانیه (null = بدون محدودیت). */
    public function timeout(): ?int;

    /**
     * فاصلهٔ retry — عدد ثابت (ثانیه) یا فهرست به‌ترتیب تلاش.
     *
     * @return int|list<int>|null
     */
    public function backoff(): int|array|null;

    /** مرز زمانی retry (retryUntil) — پس از آن job شکست می‌خورد. */
    public function retryUntil(): ?\DateTimeInterface;

    /** حذف job از صف (پس از پردازش موفق). */
    public function delete(): void;

    /** برگرداندن job به صف با تأخیر (برای retry). */
    public function release(int $delay = 0): void;

    public function isDeleted(): bool;

    public function isReleased(): bool;

    public function isDeletedOrReleased(): bool;

    /** ثبت شکست نهایی (→ failed_jobs + DLQ). */
    public function fail(?\Throwable $e = null): void;

    public function hasFailed(): bool;

    /** شناسهٔ یکتا (ULID) در payload. */
    public function uuid(): ?string;

    /** شناسهٔ job در اتصال (id ردیف / نام فایل / …). */
    public function getJobId(): int|string|null;

    /** نام کلاس دستور (displayName). */
    public function getName(): string;

    /** نام صفی که job از آن برداشته شد. */
    public function getQueue(): string;

    /** نام اتصال صف. */
    public function getConnectionName(): ?string;

    /** محدودیت نرخ (rate limit) — null یعنی بدون محدودیت. */
    public function rateLimit(): ?array;
}

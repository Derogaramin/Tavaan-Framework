<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqCategory — دستهٔ پرسش‌ها (اسلاگ یکتا + ترتیب نمایش).
 */

namespace Modules\Faq\Domain\Entities;

/**
 * FaqCategory — یک دستهٔ پرسش متداول.
 *
 * invariantها: عنوان غیرخالی؛ اسلاگ یکتا در UseCase بازبینی می‌شود
 * (Slug::unique — هم‌راستا با page/poll). حذف نرم: پرسش‌های دستهٔ
 * trash‌شده در نمای عمومی «بدون دسته» نمایش داده می‌شوند.
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
final class FaqCategory
{
    private function __construct(
        private readonly string $id,
        private string $slug,
        private string $title,
        private int $sortOrder,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $deletedAt,
    ) {
    }

    /** ساخت دسته (شناسهٔ ULID). */
    public static function make(
        string $title,
        string $slug,
        int $sortOrder = 0,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $title = \trim($title);
        if ($title === '') {
            throw new \DomainException('عنوان دسته نمی‌تواند خالی باشد.');
        }

        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            slug: $slug,
            title: $title,
            sortOrder: $sortOrder,
            createdAt: $createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * بازسازی از ردیف دیتابیس (mapper مخزن).
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            sortOrder: (int) ($row['sort_order'] ?? 0),
            createdAt: self::date($row['created_at'] ?? null),
            updatedAt: self::date($row['updated_at'] ?? null),
            deletedAt: self::date($row['deleted_at'] ?? null),
        );
    }

    // ─── رفتارسازی دامنه ──────────────────────────────────────────────────

    public function revise(?string $title = null, ?int $sortOrder = null): void
    {
        if ($title !== null) {
            $title = \trim($title);
            if ($title === '') {
                throw new \DomainException('عنوان دسته نمی‌تواند خالی باشد.');
            }
            $this->title = $title;
        }
        if ($sortOrder !== null) {
            $this->sortOrder = $sortOrder;
        }
        $this->touch();
    }

    public function changeSlug(string $slug): void
    {
        $this->slug = $slug;
        $this->touch();
    }

    public function softDelete(?\DateTimeImmutable $at = null): void
    {
        $this->deletedAt = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function restore(): void
    {
        $this->deletedAt = null;
        $this->touch();
    }

    // ─── Getterها ─────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isTrashed(): bool
    {
        return $this->deletedAt !== null;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'sort_order' => $this->sortOrder,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s.v'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s.v'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s.v'),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}

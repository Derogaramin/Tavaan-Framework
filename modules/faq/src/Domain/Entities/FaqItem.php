<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqItem — یک پرسش متداول (ریشهٔ Aggregate).
 */

namespace Modules\Faq\Domain\Entities;

use Modules\Content\Domain\Enums\ContentStatus;

/**
 * FaqItem — پرسش + پاسخ HTML پاک‌سازی‌شده + وضعیت انتشار + رأی «مفید بود».
 *
 * invariantها:
 * - question غیرخالی؛ answer در لایهٔ UseCase رندر و پاک‌سازی می‌شود.
 * - انتقال وضعیت فقط طبق ماتریس ContentStatus::canTransitionTo.
 * - شمارنده‌های helpful انباشته‌اند (رأی در هر نشست یک‌بار — در کنترلر).
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
final class FaqItem
{
    /** @var list<object> رویدادهای دامنهٔ ثبت‌شده (pull توسط UseCase) */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private ?string $categoryId,
        private string $question,
        private string $answer,
        private ContentStatus $status,
        private int $sortOrder,
        private int $helpfulYes,
        private int $helpfulNo,
        private readonly int $authorId,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $deletedAt,
    ) {
    }

    /** ساخت پرسش پیش‌نویس (شناسهٔ ULID؛ پاسخ HTML پاک‌سازی‌شده). */
    public static function draft(
        string $question,
        string $answer,
        ?string $categoryId = null,
        int $authorId = 0,
        int $sortOrder = 0,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $question = \trim($question);
        if ($question === '') {
            throw new \DomainException('پرسش نمی‌تواند خالی باشد.');
        }

        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            categoryId: $categoryId,
            question: $question,
            answer: $answer,
            status: ContentStatus::Draft,
            sortOrder: $sortOrder,
            helpfulYes: 0,
            helpfulNo: 0,
            authorId: $authorId,
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
        $categoryId = $row['category_id'] ?? null;

        return new self(
            id: (string) ($row['id'] ?? ''),
            categoryId: $categoryId === null || $categoryId === '' ? null : (string) $categoryId,
            question: (string) ($row['question'] ?? ''),
            answer: (string) ($row['answer'] ?? ''),
            status: ContentStatus::tryFrom((string) ($row['status'] ?? 'draft')) ?? ContentStatus::Draft,
            sortOrder: (int) ($row['sort_order'] ?? 0),
            helpfulYes: (int) ($row['helpful_yes'] ?? 0),
            helpfulNo: (int) ($row['helpful_no'] ?? 0),
            authorId: (int) ($row['author_id'] ?? 0),
            createdAt: self::date($row['created_at'] ?? null),
            updatedAt: self::date($row['updated_at'] ?? null),
            deletedAt: self::date($row['deleted_at'] ?? null),
        );
    }

    // ─── رفتارسازی دامنه ──────────────────────────────────────────────────

    /** ویرایش پرسش/پاسخ/دسته/ترتیب (بدون تغییر وضعیت) — رویداد faq.item.updated. */
    public function revise(
        string $question,
        ?string $answer = null,
        ?string $categoryId = null,
        ?int $sortOrder = null,
        bool $clearCategory = false,
    ): void {
        $question = \trim($question);
        if ($question === '') {
            throw new \DomainException('پرسش نمی‌تواند خالی باشد.');
        }

        $this->question = $question;
        if ($answer !== null) {
            $this->answer = $answer;
        }
        if ($clearCategory) {
            $this->categoryId = null;
        } elseif ($categoryId !== null) {
            $this->categoryId = $categoryId;
        }
        if ($sortOrder !== null) {
            $this->sortOrder = $sortOrder;
        }
        $this->touch();
        $this->recordedEvents[] = new \Modules\Faq\Domain\Events\FaqItemWasUpdated($this->id, $this->question);
    }

    /** انتشار — رویداد faq.item.published. */
    public function publish(): void
    {
        $this->assertTransition(ContentStatus::Published);
        $this->status = ContentStatus::Published;
        $this->touch();
        $this->recordedEvents[] = new \Modules\Faq\Domain\Events\FaqItemWasPublished($this->id, $this->question);
    }

    /** بایگانی (برداشتن از دید عمومی بدون حذف). */
    public function archive(): void
    {
        $this->assertTransition(ContentStatus::Archived);
        $this->status = ContentStatus::Archived;
        $this->touch();
    }

    /** بازگردانی به پیش‌نویس (تنها از archived/published مجاز است). */
    public function backToDraft(): void
    {
        $this->assertTransition(ContentStatus::Draft);
        $this->status = ContentStatus::Draft;
        $this->touch();
    }

    /** ثبت یک رأی «مفید بود» — شمارندهٔ انباشته، بدون touch (ویرایش محتوایی نیست). */
    public function recordHelpful(bool $yes): void
    {
        if ($yes) {
            $this->helpfulYes++;
        } else {
            $this->helpfulNo++;
        }
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

    // ─── پرسش‌های دامنه ───────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    /** آیا در نمای عمومی دیده می‌شود؟ (منتشرشده و غیرtrash) */
    public function isVisible(): bool
    {
        return $this->isPublished() && $this->deletedAt === null;
    }

    // ─── Getterها ─────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function categoryId(): ?string
    {
        return $this->categoryId;
    }

    public function question(): string
    {
        return $this->question;
    }

    /** پاسخ HTML پاک‌سازی‌شده. */
    public function answer(): string
    {
        return $this->answer;
    }

    public function status(): ContentStatus
    {
        return $this->status;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function helpfulYes(): int
    {
        return $this->helpfulYes;
    }

    public function helpfulNo(): int
    {
        return $this->helpfulNo;
    }

    public function authorId(): int
    {
        return $this->authorId;
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

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->categoryId,
            'question' => $this->question,
            'answer' => $this->answer,
            'status' => $this->status->value,
            'sort_order' => $this->sortOrder,
            'helpful_yes' => $this->helpfulYes,
            'helpful_no' => $this->helpfulNo,
            'author_id' => $this->authorId,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s.v'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s.v'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s.v'),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function assertTransition(ContentStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(
                \sprintf('Invalid FAQ item status transition: %s → %s', $this->status->value, $target->value),
            );
        }
    }

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

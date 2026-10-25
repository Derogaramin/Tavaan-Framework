<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * Course — ریشهٔ Aggregate دوره (DOC-095 §1.2).
 */

namespace Modules\Lms\Domain\Entities;

use Modules\Lms\Domain\Enums\CourseLevel;
use Modules\Lms\Domain\Enums\CourseStatus;
use Modules\Lms\Domain\Events\CourseWasPublished;
use Modules\Lms\Domain\Events\CourseWasUpdated;
use Modules\Lms\Domain\ValueObjects\Money;

/**
 * Course — دورهٔ آموزشی (Section/Lesson/Prerequisite زیرمجموعهٔ آن‌اند).
 *
 * invariantها:
 * - انتقال وضعیت فقط طبق ماتریس CourseStatus
 * - دورهٔ رایگان: price صفر و is_free=true (همگام)
 * - slug یکتا (تولید با Slug ماژول content — UseCase)
 * - انتشار فقط با حداقل یک درس (UseCase بررسی می‌کند)
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class Course
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private string $slug,
        private string $title,
        private ?string $summary,
        private ?string $description,
        private readonly int $instructorId,
        private ?int $categoryId,
        private CourseLevel $level,
        private string $language,
        private Money $price,
        private bool $isFree,
        private CourseStatus $status,
        private ?string $thumbnail,
        private int $totalDuration,
        private float $ratingAvg,
        private int $studentCount,
        private ?\DateTimeImmutable $publishedAt,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        /** @var list<string> شناسهٔ دوره‌های پیش‌نیاز */
        private array $prerequisiteIds = [],
    ) {
    }

    /** ساخت دورهٔ پیش‌نویس (DOC-095 §3 — instructor دوره می‌سازد). */
    public static function draft(
        string $slug,
        string $title,
        int $instructorId,
        ?string $summary = null,
        ?string $description = null,
        ?int $categoryId = null,
        CourseLevel $level = CourseLevel::Beginner,
        string $language = 'fa',
        ?Money $price = null,
        ?string $thumbnail = null,
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان دوره نمی‌تواند خالی باشد.');
        }

        $price ??= Money::zero();

        return new self(
            id: \Tavaan\Support\Ulid::generate($at)->toString(),
            slug: $slug,
            title: $title,
            summary: $summary,
            description: $description,
            instructorId: $instructorId,
            categoryId: $categoryId,
            level: $level,
            language: $language,
            price: $price,
            isFree: $price->isZero(),
            status: CourseStatus::Draft,
            thumbnail: $thumbnail,
            totalDuration: 0,
            ratingAvg: 0.0,
            studentCount: 0,
            publishedAt: null,
            createdAt: $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            updatedAt: null,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $prerequisiteIds
     */
    public static function fromRow(array $row, array $prerequisiteIds = []): self
    {
        $currency = (string) ($row['currency'] ?? 'IRR');
        $price = Money::of((int) ($row['price'] ?? 0), $currency);

        return new self(
            id: (string) ($row['id'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            summary: isset($row['summary']) ? (string) $row['summary'] : null,
            description: isset($row['description']) && $row['description'] !== null ? (string) $row['description'] : null,
            instructorId: (int) ($row['instructor_id'] ?? 0),
            categoryId: isset($row['category_id']) && $row['category_id'] !== null ? (int) $row['category_id'] : null,
            level: CourseLevel::tryFromString((string) ($row['level'] ?? 'beginner')),
            language: (string) ($row['language'] ?? 'fa'),
            price: $price,
            isFree: (bool) ($row['is_free'] ?? $price->isZero()),
            status: CourseStatus::tryFrom((string) ($row['status'] ?? 'draft')) ?? CourseStatus::Draft,
            thumbnail: isset($row['thumbnail']) && $row['thumbnail'] !== null && $row['thumbnail'] !== '' ? (string) $row['thumbnail'] : null,
            totalDuration: (int) ($row['total_duration'] ?? 0),
            ratingAvg: (float) ($row['rating_avg'] ?? 0),
            studentCount: (int) ($row['student_count'] ?? 0),
            publishedAt: self::date($row['published_at'] ?? null),
            createdAt: self::date($row['created_at'] ?? null),
            updatedAt: self::date($row['updated_at'] ?? null),
            prerequisiteIds: $prerequisiteIds,
        );
    }

    // ─── رفتاردامنه ───────────────────────────────────────────────────────

    /** ویرایش ویژگی‌های دوره (بدون تغییر مدرس). */
    public function revise(
        string $title,
        ?string $slug = null,
        ?string $summary = null,
        ?string $description = null,
        ?int $categoryId = null,
        ?CourseLevel $level = null,
        ?string $language = null,
        ?Money $price = null,
        ?string $thumbnail = null,
        ?array $prerequisiteIds = null,
    ): void {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان دوره نمی‌تواند خالی باشد.');
        }

        $this->title = $title;
        $this->slug = $slug ?? $this->slug;
        $this->summary = $summary;
        $this->description = $description;
        $this->categoryId = $categoryId;
        $this->level = $level ?? $this->level;
        $this->language = $language ?? $this->language;
        if ($price !== null) {
            $this->price = $price;
            $this->isFree = $price->isZero();
        }
        $this->thumbnail = $thumbnail;
        if ($prerequisiteIds !== null) {
            $this->prerequisiteIds = \array_values(\array_unique(\array_filter(
                $prerequisiteIds,
                fn (string $pid): bool => $pid !== '' && $pid !== $this->id,   // 🔒 پیش‌نیازِ خود = چرخه
            )));
        }
        $this->touch();

        $this->record(new CourseWasUpdated($this->id, $this->slug, $this->title, $this->status->value));
    }

    /** انتشار دوره (draft/archived → published). */
    public function publish(?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(CourseStatus::Published);
        $this->publishedAt ??= $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->touch();

        $this->record(new CourseWasPublished(
            $this->id,
            $this->slug,
            $this->title,
            $this->instructorId,
            $this->price->minor,
            $this->price->currency,
            $this->isFree,
        ));
    }

    /** بایگانی (published → archived). */
    public function archive(): void
    {
        $this->transitionTo(CourseStatus::Archived);
        $this->touch();
        $this->record(new CourseWasUpdated($this->id, $this->slug, $this->title, $this->status->value));
    }

    /** بازگشت به پیش‌نویس (published → draft). */
    public function unpublish(): void
    {
        $this->transitionTo(CourseStatus::Draft);
        $this->touch();
        $this->record(new CourseWasUpdated($this->id, $this->slug, $this->title, $this->status->value));
    }

    /** بازگردانی مدت‌زمان کل (جمع درس‌ها — توسط UseCase). */
    public function refreshDuration(int $totalMinutes): void
    {
        $this->totalDuration = \max(0, $totalMinutes);
        $this->touch();
    }

    /** شمارش دانشجو (نگهداری توسط repository/UseCase). */
    public function applyStudentCount(int $count): void
    {
        $this->studentCount = \max(0, $count);
    }

    // ─── پرسش‌ها ──────────────────────────────────────────────────────────

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

    public function summary(): ?string
    {
        return $this->summary;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function instructorId(): int
    {
        return $this->instructorId;
    }

    public function categoryId(): ?int
    {
        return $this->categoryId;
    }

    public function level(): CourseLevel
    {
        return $this->level;
    }

    public function language(): string
    {
        return $this->language;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function isFree(): bool
    {
        return $this->isFree;
    }

    public function isPaid(): bool
    {
        return !$this->isFree;
    }

    public function status(): CourseStatus
    {
        return $this->status;
    }

    public function isPublished(): bool
    {
        return $this->status === CourseStatus::Published;
    }

    public function thumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function totalDuration(): int
    {
        return $this->totalDuration;
    }

    public function ratingAvg(): float
    {
        return $this->ratingAvg;
    }

    public function studentCount(): int
    {
        return $this->studentCount;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<string> */
    public function prerequisiteIds(): array
    {
        return $this->prerequisiteIds;
    }

    /** @return list<object> رویدادهای ثبت‌شده (بدون پاک‌سازی) */
    public function events(): array
    {
        return $this->recordedEvents;
    }

    /** @return list<object> رویدادها را برمی‌گرداند و پاک می‌کند */
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
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'description' => $this->description,
            'instructor_id' => $this->instructorId,
            'category_id' => $this->categoryId,
            'level' => $this->level->value,
            'language' => $this->language,
            'price' => $this->price->minor,
            'currency' => $this->price->currency,
            'is_free' => $this->isFree,
            'status' => $this->status->value,
            'thumbnail' => $this->thumbnail,
            'total_duration' => $this->totalDuration,
            'rating_avg' => $this->ratingAvg,
            'student_count' => $this->studentCount,
            'published_at' => self::fmt($this->publishedAt),
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function transitionTo(CourseStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(\sprintf(
                'انتقال وضعیت دوره نامجاز: %s → %s (دوره %s)',
                $this->status->value,
                $target->value,
                $this->slug,
            ));
        }

        $this->status = $target;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    private static function fmt(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        try {
            return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}

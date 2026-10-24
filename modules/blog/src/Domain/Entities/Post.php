<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * Post — ریشهٔ Aggregate مقاله (DOC-091 §1.1).
 */

namespace Modules\Blog\Domain\Entities;

use Modules\Blog\Domain\Events\PostWasPublished;
use Modules\Blog\Domain\Events\PostWasUpdated;
use Modules\Comment\Contracts\CommentableInterface;
use Modules\Content\Domain\Enums\ContentStatus;
use Modules\Content\Domain\ValueObjects\SeoMetadata;
use Modules\Media\Contracts\MediaAttachableInterface;

/**
 * Post — ریشهٔ Aggregate با انتقال‌های وضعیت و رویدادهای دامنهٔ ثبت‌شده.
 *
 * invariantها:
 * - `published`/`archived` حتماً `published_at` دارند.
 * - `scheduled` حتماً `published_at` آینده دارد (زمان‌بندی انتشار — DOC-091 §6).
 * - انتقال وضعیت فقط طبق ماتریس ContentStatus::canTransitionTo.
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class Post implements CommentableInterface, MediaAttachableInterface
{
    /** @var list<object> رویدادهای دامنهٔ ثبت‌شده (pull توسط UseCase) */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private string $slug,
        private string $title,
        private ?string $excerpt,
        private ?string $body,
        private readonly int $authorId,
        private ?int $categoryId,
        private ContentStatus $status,
        private ?string $featuredImage,
        private int $viewCount,
        private ?\DateTimeImmutable $publishedAt,
        private SeoMetadata $seo,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $deletedAt,
    ) {
    }

    /** ساخت مقالهٔ پیش‌نویس جدید (شناسهٔ ULID). */
    public static function draft(
        string $slug,
        string $title,
        int $authorId,
        ?string $excerpt = null,
        ?string $body = null,
        ?int $categoryId = null,
        ?string $featuredImage = null,
        ?SeoMetadata $seo = null,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            slug: $slug,
            title: $title,
            excerpt: $excerpt,
            body: $body,
            authorId: $authorId,
            categoryId: $categoryId,
            status: ContentStatus::Draft,
            featuredImage: $featuredImage,
            viewCount: 0,
            publishedAt: null,
            seo: $seo ?? new SeoMetadata(),
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
            excerpt: isset($row['excerpt']) ? (string) $row['excerpt'] : null,
            body: isset($row['body']) ? (string) $row['body'] : null,
            authorId: (int) ($row['author_id'] ?? 0),
            categoryId: isset($row['category_id']) && $row['category_id'] !== null ? (int) $row['category_id'] : null,
            status: ContentStatus::tryFrom((string) ($row['status'] ?? 'draft')) ?? ContentStatus::Draft,
            featuredImage: isset($row['featured_image']) && $row['featured_image'] !== null && $row['featured_image'] !== ''
                ? (string) $row['featured_image']
                : null,
            viewCount: (int) ($row['view_count'] ?? 0),
            publishedAt: self::date($row['published_at'] ?? null),
            seo: SeoMetadata::make(
                title: (string) ($row['seo_title'] ?? ''),
                plainBody: '',
                description: (string) ($row['seo_description'] ?? ''),
                keywords: (string) ($row['seo_keywords'] ?? ''),
            ),
            createdAt: self::date($row['created_at'] ?? null),
            updatedAt: self::date($row['updated_at'] ?? null),
            deletedAt: self::date($row['deleted_at'] ?? null),
        );
    }

    // ─── رفتارسازی دامنه ──────────────────────────────────────────────────

    /** ویرایش محتوا (بدون تغییر وضعیت) — رویداد PostWasUpdated. */
    public function revise(
        string $title,
        ?string $excerpt,
        ?string $body,
        ?int $categoryId,
        ?string $featuredImage = null,
        ?SeoMetadata $seo = null,
        ?\DateTimeImmutable $at = null,
    ): void {
        $this->title = $title;
        $this->excerpt = $excerpt;
        $this->body = $body;
        $this->categoryId = $categoryId;
        if ($featuredImage !== null) {
            $this->featuredImage = $featuredImage !== '' ? $featuredImage : null;
        }
        if ($seo !== null) {
            $this->seo = $seo;
        }
        $this->touch($at);

        $this->recordedEvents[] = new PostWasUpdated(
            postId: $this->id,
            slug: $this->slug,
            title: $this->title,
            authorId: $this->authorId,
            status: $this->status,
            at: $this->updatedAt ?? $this->createdAt,
        );
    }

    /** انتشار فوری (یا با تاریخ مشخص/backdating) — رویداد PostWasPublished. */
    public function publish(?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(ContentStatus::Published, $at);
        $this->publishedAt = $at ?? $this->publishedAt ?? $this->now();

        $this->recordedEvents[] = new PostWasPublished(
            postId: $this->id,
            slug: $this->slug,
            title: $this->title,
            authorId: $this->authorId,
            publishedAt: $this->publishedAt,
        );
    }

    /** زمان‌بندی انتشار در آینده (DOC-091 §6). */
    public function schedule(\DateTimeImmutable $publishAt): void
    {
        $this->transitionTo(ContentStatus::Scheduled, $publishAt);
        $this->publishedAt = $publishAt;
    }

    public function archive(?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(ContentStatus::Archived, $at);
    }

    /** بازگردانی به پیش‌نویس (unpublish). */
    public function backToDraft(?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(ContentStatus::Draft, $at);
    }

    /** تغییر slug (پس از بررسی یکتایی توسط UseCase). */
    public function changeSlug(string $slug): void
    {
        $slug = \trim($slug);
        if ($slug === '') {
            throw new \DomainException('slug نمی‌تواند خالی باشد.');
        }
        $this->slug = $slug;
    }

    public function incrementViews(int $by = 1): void
    {
        $this->viewCount = \max(0, $this->viewCount + $by);
    }

    /** حذف نرم (soft delete). */
    public function softDelete(?\DateTimeImmutable $at = null): void
    {
        $this->deletedAt = $at ?? $this->now();
        $this->touch($at);
    }

    public function restore(?\DateTimeImmutable $at = null): void
    {
        $this->deletedAt = null;
        $this->touch($at);
    }

    /** آیا زمان انتشار scheduled فرا رسیده است؟ */
    public function isDueForPublishing(?\DateTimeImmutable $now = null): bool
    {
        return $this->status === ContentStatus::Scheduled
            && $this->publishedAt !== null
            && ($now ?? $this->now()) >= $this->publishedAt;
    }

    // ─── CommentableInterface (ماژول comment) ────────────────────────────

    public function commentableType(): string
    {
        return 'blog_post';
    }

    public function commentableId(): string
    {
        return $this->id;
    }

    public function commentsOpen(): bool
    {
        return $this->status->isPublic() && $this->deletedAt === null;
    }

    // ─── MediaAttachableInterface (ماژول media) ──────────────────────────

    public function mediaSubjectType(): string
    {
        return 'blog_post';
    }

    public function mediaSubjectId(): string
    {
        return $this->id;
    }

    // ─── Getters ──────────────────────────────────────────────────────────

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

    public function excerpt(): ?string
    {
        return $this->excerpt;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function authorId(): int
    {
        return $this->authorId;
    }

    public function categoryId(): ?int
    {
        return $this->categoryId;
    }

    public function status(): ContentStatus
    {
        return $this->status;
    }

    public function featuredImage(): ?string
    {
        return $this->featuredImage;
    }

    public function viewCount(): int
    {
        return $this->viewCount;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function seo(): SeoMetadata
    {
        return $this->seo;
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

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published && $this->deletedAt === null;
    }

    public function isTrashed(): bool
    {
        return $this->deletedAt !== null;
    }

    /** تخلیهٔ رویدادهای دامنهٔ ثبت‌شده (یک‌بار مصرف). @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /**
     * نگاشت به ردیف دیتابیس (mapper مخزن).
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'author_id' => $this->authorId,
            'category_id' => $this->categoryId,
            'status' => $this->status->value,
            'featured_image' => $this->featuredImage,
            'view_count' => $this->viewCount,
            'published_at' => self::fmt($this->publishedAt),
            'seo_title' => $this->seo->title,
            'seo_description' => $this->seo->description,
            'seo_keywords' => $this->seo->keywords,
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
            'deleted_at' => self::fmt($this->deletedAt),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function transitionTo(ContentStatus $target, ?\DateTimeImmutable $at = null): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(\sprintf(
                'انتقال وضعیت نامجاز: %s → %s (مقاله %s)',
                $this->status->value,
                $target->value,
                $this->id,
            ));
        }

        $this->status = $target;
        $this->touch($at);
    }

    private function touch(?\DateTimeImmutable $at = null): void
    {
        $this->updatedAt = $at ?? $this->now();
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if (!\is_string($value) || \trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }

    private static function fmt(?\DateTimeImmutable $dt): ?string
    {
        return $dt?->format('Y-m-d H:i:s.v');
    }
}

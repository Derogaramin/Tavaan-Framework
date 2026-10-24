<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * Article — ریشهٔ Aggregate خبر با workflow انتشار (DOC-092 §1/§3).
 */

namespace Modules\News\Domain\Entities;

use Modules\Comment\Contracts\CommentableInterface;
use Modules\Content\Domain\ValueObjects\SeoMetadata;
use Modules\Media\Contracts\MediaAttachableInterface;
use Modules\News\Domain\Enums\ArticleStatus;
use Modules\News\Domain\Events\ArticleWasApproved;
use Modules\News\Domain\Events\ArticleWasPublished;
use Modules\News\Domain\Events\ArticleWasRejected;
use Modules\News\Domain\Events\ArticleWasSubmitted;
use Modules\News\Domain\Events\ArticleWasUpdated;
use Modules\News\Domain\Events\BreakingNewsWasPublished;

/**
 * Article — ریشهٔ Aggregate با انتقال‌های workflow و رویدادهای دامنهٔ ثبت‌شده.
 *
 * invariantها:
 * - `published` حتماً `published_at` دارد (و editor_id پر شده — تأییدکننده).
 * - `approved` می‌تواند `scheduled_at` آینده داشته باشد → انتشار خودکار
 *   با `tvn news:publish-scheduled` (DOC-092 §3).
 * - `is_breaking` فقط روی `published` معنی دارد (DOC-092 §6).
 * - انتقال وضعیت فقط طبق ماتریس ArticleStatus::canTransitionTo.
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class Article implements CommentableInterface, MediaAttachableInterface
{
    /** @var list<object> رویدادهای دامنهٔ ثبت‌شده (pull-once توسط UseCase) */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private string $slug,
        private string $title,
        private ?string $lead,
        private ?string $body,
        private readonly int $authorId,
        private ?int $editorId,
        private ?int $categoryId,
        private ArticleStatus $status,
        private bool $isBreaking,
        private bool $isFeatured,
        private ?string $featuredImage,
        private array $gallery,
        private int $viewCount,
        private ?\DateTimeImmutable $publishedAt,
        private ?\DateTimeImmutable $scheduledAt,
        private SeoMetadata $seo,
        private ?string $rejectReason,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $deletedAt,
    ) {
    }

    /** ساخت خبر پیش‌نویس جدید (شناسهٔ ULID). */
    public static function draft(
        string $slug,
        string $title,
        int $authorId,
        ?string $lead = null,
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
            lead: $lead,
            body: $body,
            authorId: $authorId,
            editorId: null,
            categoryId: $categoryId,
            status: ArticleStatus::Draft,
            isBreaking: false,
            isFeatured: false,
            featuredImage: $featuredImage,
            gallery: [],
            viewCount: 0,
            publishedAt: null,
            scheduledAt: null,
            seo: $seo ?? new SeoMetadata(),
            rejectReason: null,
            createdAt: $createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * بازسازی از ردیف دیتابیس.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $gallery = [];
        if (isset($row['gallery']) && \is_string($row['gallery']) && $row['gallery'] !== '') {
            $decoded = \json_decode($row['gallery'], true);
            if (\is_array($decoded)) {
                $gallery = \array_values(\array_filter(\array_map('strval', $decoded), static fn (string $v): bool => $v !== ''));
            }
        } elseif (isset($row['gallery']) && \is_array($row['gallery'])) {
            $gallery = \array_values(\array_map('strval', $row['gallery']));
        }

        return new self(
            id: (string) ($row['id'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            lead: isset($row['lead']) ? (string) $row['lead'] : null,
            body: isset($row['body']) ? (string) $row['body'] : null,
            authorId: (int) ($row['author_id'] ?? 0),
            editorId: isset($row['editor_id']) && $row['editor_id'] !== null ? (int) $row['editor_id'] : null,
            categoryId: isset($row['category_id']) && $row['category_id'] !== null ? (int) $row['category_id'] : null,
            status: ArticleStatus::tryFrom((string) ($row['status'] ?? 'draft')) ?? ArticleStatus::Draft,
            isBreaking: (bool) ($row['is_breaking'] ?? false),
            isFeatured: (bool) ($row['is_featured'] ?? false),
            featuredImage: isset($row['featured_image']) && \is_string($row['featured_image']) && $row['featured_image'] !== ''
                ? $row['featured_image']
                : null,
            gallery: $gallery,
            viewCount: (int) ($row['view_count'] ?? 0),
            publishedAt: self::date($row['published_at'] ?? null),
            scheduledAt: self::date($row['scheduled_at'] ?? null),
            seo: SeoMetadata::make(
                title: (string) ($row['seo_title'] ?? ''),
                plainBody: '',
                description: (string) ($row['seo_description'] ?? ''),
            ),
            rejectReason: isset($row['reject_reason']) && $row['reject_reason'] !== null ? (string) $row['reject_reason'] : null,
            createdAt: self::date($row['created_at'] ?? null),
            updatedAt: self::date($row['updated_at'] ?? null),
            deletedAt: self::date($row['deleted_at'] ?? null),
        );
    }

    // ─── workflow (DOC-092 §3) ────────────────────────────────────────────

    /** نویسنده خبر را برای بازبینی ارسال می‌کند. */
    public function submitForReview(?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(ArticleStatus::Review, $at);
        $this->rejectReason = null;

        $this->recordedEvents[] = new ArticleWasSubmitted(
            articleId: $this->id,
            slug: $this->slug,
            title: $this->title,
            authorId: $this->authorId,
        );
    }

    /** ویراستار خبر را تأیید می‌کند (با زمان‌بندی اختیاری انتشار). */
    public function approve(int $editorId, ?\DateTimeImmutable $scheduledAt = null, ?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(ArticleStatus::Approved, $at);
        $this->editorId = $editorId;
        $this->scheduledAt = $scheduledAt;
        $this->rejectReason = null;

        $this->recordedEvents[] = new ArticleWasApproved(
            articleId: $this->id,
            slug: $this->slug,
            title: $this->title,
            authorId: $this->authorId,
            editorId: $editorId,
            scheduledAt: $scheduledAt,
        );
    }

    /** ویراستار خبر را رد می‌کند → بازگشت به پیش‌نویس. */
    public function reject(int $editorId, string $reason = '', ?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(ArticleStatus::Draft, $at);
        $this->editorId = $editorId;
        $this->rejectReason = $reason !== '' ? $reason : null;

        $this->recordedEvents[] = new ArticleWasRejected(
            articleId: $this->id,
            slug: $this->slug,
            title: $this->title,
            authorId: $this->authorId,
            editorId: $editorId,
            reason: $this->rejectReason ?? '',
        );
    }

    /** سردبیر منتشر می‌کند (فوری یا با تاریخ مشخص/backdating). */
    public function publish(int $editorId, ?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(ArticleStatus::Published, $at);
        $this->editorId = $editorId;
        $this->publishedAt = $at ?? $this->publishedAt ?? $this->now();
        $this->scheduledAt = null;

        $this->recordedEvents[] = new ArticleWasPublished(
            articleId: $this->id,
            slug: $this->slug,
            title: $this->title,
            authorId: $this->authorId,
            editorId: $editorId,
            publishedAt: $this->publishedAt,
            isBreaking: $this->isBreaking,
        );

        if ($this->isBreaking) {
            $this->recordedEvents[] = new BreakingNewsWasPublished(
                articleId: $this->id,
                slug: $this->slug,
                title: $this->title,
                lead: $this->lead ?? '',
                publishedAt: $this->publishedAt,
            );
        }
    }

    public function archive(?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(ArticleStatus::Archived, $at);
        $this->isBreaking = false;
    }

    /** برداشتن از انتشار → پیش‌نویس. */
    public function unpublish(?\DateTimeImmutable $at = null): void
    {
        $this->transitionTo(ArticleStatus::Draft, $at);
        $this->isBreaking = false;
    }

    /**
     * علامت خبر فوری (DOC-092 §6) — فقط روی خبر منتشرشده/تأییدشده.
     *
     * @return bool آیا تغییر ایجاد شد (برای broadcast)
     */
    public function setBreaking(bool $breaking): bool
    {
        if ($breaking && !$this->status->isPublic() && $this->status !== ArticleStatus::Approved) {
            throw new \DomainException('خبر فوری فقط برای خبر منتشرشده/تأییدشده مجاز است (خبر ' . $this->id . ' در وضعیت ' . $this->status->value . ' است).');
        }

        $changed = $this->isBreaking !== $breaking;
        $this->isBreaking = $breaking;

        if ($breaking && $changed && $this->status->isPublic()) {
            $this->recordedEvents[] = new BreakingNewsWasPublished(
                articleId: $this->id,
                slug: $this->slug,
                title: $this->title,
                lead: $this->lead ?? '',
                publishedAt: $this->publishedAt,
            );
        }

        return $changed;
    }

    public function setFeatured(bool $featured): void
    {
        $this->isFeatured = $featured;
    }

    // ─── ویرایش محتوا ─────────────────────────────────────────────────────

    /**
     * ویرایش محتوایی (بدون تغییر وضعیت workflow).
     *
     * @param list<string> $gallery ULID رسانه‌ها
     */
    public function revise(
        string $title,
        ?string $lead,
        ?string $body,
        ?int $categoryId,
        ?string $featuredImage = null,
        ?array $gallery = null,
        ?SeoMetadata $seo = null,
        ?\DateTimeImmutable $at = null,
    ): void {
        $this->title = $title;
        $this->lead = $lead;
        $this->body = $body;
        $this->categoryId = $categoryId;
        if ($featuredImage !== null) {
            $this->featuredImage = $featuredImage !== '' ? $featuredImage : null;
        }
        if ($gallery !== null) {
            $this->gallery = \array_values(\array_filter(\array_map('strval', $gallery), static fn (string $v): bool => $v !== ''));
        }
        if ($seo !== null) {
            $this->seo = $seo;
        }
        $this->touch($at);

        $this->recordedEvents[] = new ArticleWasUpdated(
            articleId: $this->id,
            slug: $this->slug,
            title: $this->title,
            authorId: $this->authorId,
            status: $this->status,
        );
    }

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

    public function softDelete(?\DateTimeImmutable $at = null): void
    {
        $this->deletedAt = $at ?? $this->now();
        $this->isBreaking = false;
        $this->touch($at);
    }

    public function restore(?\DateTimeImmutable $at = null): void
    {
        $this->deletedAt = null;
        $this->touch($at);
    }

    /** آیا زمان انتشار زمان‌بندی‌شده رسیده است؟ (approved + scheduled_at ≤ now) */
    public function isDueForPublishing(?\DateTimeImmutable $now = null): bool
    {
        return $this->status === ArticleStatus::Approved
            && $this->scheduledAt !== null
            && ($now ?? $this->now()) >= $this->scheduledAt;
    }

    // ─── قراردادها ────────────────────────────────────────────────────────

    public function commentableType(): string
    {
        return 'news_article';
    }

    public function commentableId(): string
    {
        return $this->id;
    }

    public function commentsOpen(): bool
    {
        return $this->status->isPublic() && $this->deletedAt === null;
    }

    public function mediaSubjectType(): string
    {
        return 'news_article';
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

    public function lead(): ?string
    {
        return $this->lead;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function authorId(): int
    {
        return $this->authorId;
    }

    public function editorId(): ?int
    {
        return $this->editorId;
    }

    public function categoryId(): ?int
    {
        return $this->categoryId;
    }

    public function status(): ArticleStatus
    {
        return $this->status;
    }

    public function isBreaking(): bool
    {
        return $this->isBreaking;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function featuredImage(): ?string
    {
        return $this->featuredImage;
    }

    /** @return list<string> */
    public function gallery(): array
    {
        return $this->gallery;
    }

    public function viewCount(): int
    {
        return $this->viewCount;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function scheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function seo(): SeoMetadata
    {
        return $this->seo;
    }

    public function rejectReason(): ?string
    {
        return $this->rejectReason;
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
        return $this->status === ArticleStatus::Published && $this->deletedAt === null;
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
     * نگاشت به ردیف دیتابیس.
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'lead' => $this->lead,
            'body' => $this->body,
            'author_id' => $this->authorId,
            'editor_id' => $this->editorId,
            'category_id' => $this->categoryId,
            'status' => $this->status->value,
            'is_breaking' => $this->isBreaking ? 1 : 0,
            'is_featured' => $this->isFeatured ? 1 : 0,
            'featured_image' => $this->featuredImage,
            'gallery' => $this->gallery === [] ? null : (string) \json_encode($this->gallery, JSON_THROW_ON_ERROR),
            'view_count' => $this->viewCount,
            'published_at' => self::fmt($this->publishedAt),
            'scheduled_at' => self::fmt($this->scheduledAt),
            'seo_title' => $this->seo->title,
            'seo_description' => $this->seo->description,
            'reject_reason' => $this->rejectReason,
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
            'deleted_at' => self::fmt($this->deletedAt),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function transitionTo(ArticleStatus $target, ?\DateTimeImmutable $at = null): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(\sprintf(
                'انتقال workflow نامجاز: %s → %s (خبر %s)',
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

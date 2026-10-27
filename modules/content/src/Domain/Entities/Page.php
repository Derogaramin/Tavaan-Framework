<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * Page — ریشهٔ Aggregate صفحهٔ CMS.
 */

namespace Modules\Content\Domain\Entities;

use Modules\Comment\Contracts\CommentableInterface;
use Modules\Content\Domain\Enums\ContentStatus;
use Modules\Content\Domain\ValueObjects\SeoMetadata;

/**
 * Page — صفحهٔ ایستا/محتوایی (درباره ما، شرایط استفاده، فرود و …).
 *
 * invariantها:
 * - `published`/`archived` حتماً `published_at` دارند.
 * - انتقال وضعیت فقط طبق ماتریس ContentStatus::canTransitionTo.
 *
 * Commentable: نوع پلی‌مورفیک `page` — هم‌راستا با allowlist پیش‌فرض
 * رجیستری ماژول نظر (config comment.types.page).
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class Page implements CommentableInterface
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
        private ContentStatus $status,
        private int $viewCount,
        private ?\DateTimeImmutable $publishedAt,
        private SeoMetadata $seo,
        private bool $commentsOpen,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $deletedAt,
    ) {
    }

    /** ساخت صفحهٔ پیش‌نویس جدید (شناسهٔ ULID). */
    public static function draft(
        string $slug,
        string $title,
        int $authorId,
        ?string $excerpt = null,
        ?string $body = null,
        ?SeoMetadata $seo = null,
        bool $commentsOpen = true,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            slug: $slug,
            title: $title,
            excerpt: $excerpt,
            body: $body,
            authorId: $authorId,
            status: ContentStatus::Draft,
            viewCount: 0,
            publishedAt: null,
            seo: $seo ?? new SeoMetadata(),
            commentsOpen: $commentsOpen,
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
            excerpt: isset($row['excerpt']) && $row['excerpt'] !== null ? (string) $row['excerpt'] : null,
            body: isset($row['body']) && $row['body'] !== null ? (string) $row['body'] : null,
            authorId: (int) ($row['author_id'] ?? 0),
            status: ContentStatus::tryFrom((string) ($row['status'] ?? 'draft')) ?? ContentStatus::Draft,
            viewCount: (int) ($row['view_count'] ?? 0),
            publishedAt: self::date($row['published_at'] ?? null),
            seo: SeoMetadata::make(
                title: (string) ($row['seo_title'] ?? ''),
                plainBody: '',
                description: (string) ($row['seo_description'] ?? ''),
                keywords: (string) ($row['seo_keywords'] ?? ''),
            ),
            commentsOpen: (bool) ($row['comments_open'] ?? true),
            createdAt: self::date($row['created_at'] ?? null),
            updatedAt: self::date($row['updated_at'] ?? null),
            deletedAt: self::date($row['deleted_at'] ?? null),
        );
    }

    // ─── رفتارسازی دامنه ──────────────────────────────────────────────────

    /** ویرایش محتوا (بدون تغییر وضعیت). */
    public function revise(
        string $title,
        ?string $slug,
        ?string $excerpt,
        ?string $body,
        ?SeoMetadata $seo = null,
        ?bool $commentsOpen = null,
    ): void {
        $this->title = $title;
        if ($slug !== null && $slug !== '') {
            $this->slug = $slug;
        }
        $this->excerpt = $excerpt;
        $this->body = $body;
        if ($seo !== null) {
            $this->seo = $seo;
        }
        if ($commentsOpen !== null) {
            $this->commentsOpen = $commentsOpen;
        }
        $this->touch();
        $this->recordedEvents[] = new \Modules\Content\Domain\Events\PageWasUpdated($this->id, $this->slug, $this->title);
    }

    /** انتشار — طبق ماتریس انتقال وضعیت. */
    public function publish(?\DateTimeImmutable $at = null): void
    {
        $this->assertTransition(ContentStatus::Published);
        $this->status = ContentStatus::Published;
        $this->publishedAt = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->touch();
        $this->recordedEvents[] = new \Modules\Content\Domain\Events\PageWasPublished($this->id, $this->slug, $this->title);
    }

    public function archive(?\DateTimeImmutable $at = null): void
    {
        $this->assertTransition(ContentStatus::Archived);
        $this->status = ContentStatus::Archived;
        $this->publishedAt = $this->publishedAt ?? $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->touch();
    }

    public function backToDraft(?\DateTimeImmutable $at = null): void
    {
        $this->assertTransition(ContentStatus::Draft);
        $this->status = ContentStatus::Draft;
        $this->touch();
    }

    public function changeSlug(string $slug): void
    {
        $this->slug = $slug;
        $this->touch();
    }

    public function incrementViews(int $by = 1): void
    {
        $this->viewCount = \max(0, $this->viewCount + $by);
    }

    public function softDelete(?\DateTimeImmutable $at = null): void
    {
        $this->deletedAt = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function restore(?\DateTimeImmutable $at = null): void
    {
        $this->deletedAt = null;
        $this->touch();
    }

    // ─── CommentableInterface ─────────────────────────────────────────────

    public function commentableType(): string
    {
        return 'page';
    }

    public function commentableId(): string
    {
        return $this->slug;
    }

    public function commentsOpen(): bool
    {
        return $this->commentsOpen && $this->status === ContentStatus::Published && $this->deletedAt === null;
    }

    /** پرچم خامِ «باز بودن نظرات» (فارغ از وضعیت) — مخصوص فرم مدیریت. */
    public function commentsOpenFlag(): bool
    {
        return $this->commentsOpen;
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

    public function status(): ContentStatus
    {
        return $this->status;
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
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'author_id' => $this->authorId,
            'status' => $this->status->value,
            'view_count' => $this->viewCount,
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s.v'),
            'seo_title' => $this->seo->title !== '' ? $this->seo->title : null,
            'seo_description' => $this->seo->description !== '' ? $this->seo->description : null,
            'seo_keywords' => $this->seo->keywords !== '' ? $this->seo->keywords : null,
            'comments_open' => $this->commentsOpen ? 1 : 0,
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
                \sprintf('Invalid status transition: %s → %s', $this->status->value, $target->value),
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

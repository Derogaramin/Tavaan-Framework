<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * Thread — ریشهٔ Aggregate موضوع گفت‌وجو (DOC-094 §1/§2).
 */

namespace Modules\Forum\Domain\Entities;

/**
 * Thread — یک موضوع گفت‌وجو در تالار.
 *
 * invariantها:
 * - موضوع قفل (is_locked) پاسخ جدید نمی‌پذیرد (مگر با مجوز moderation).
 * - `is_solved` وقتی true است که دقیقاً یک پست `is_solution` دارد.
 * - view_count/reply_count/last_post_at با نوشتن‌ها همگام می‌مانند.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class Thread
{
    private function __construct(
        private readonly string $id,
        private int $forumId,
        private readonly int $authorId,
        private string $title,
        private string $slug,
        private bool $isPinned,
        private bool $isLocked,
        private bool $isSolved,
        private int $viewCount,
        private int $replyCount,
        private ?\DateTimeImmutable $lastPostAt,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        int $forumId,
        int $authorId,
        string $title,
        string $slug,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            forumId: $forumId,
            authorId: $authorId,
            title: $title,
            slug: $slug,
            isPinned: false,
            isLocked: false,
            isSolved: false,
            viewCount: 0,
            replyCount: 0,
            lastPostAt: null,
            createdAt: $createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            updatedAt: null,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            forumId: (int) ($row['forum_id'] ?? 0),
            authorId: (int) ($row['author_id'] ?? 0),
            title: (string) ($row['title'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
            isPinned: (bool) ($row['is_pinned'] ?? false),
            isLocked: (bool) ($row['is_locked'] ?? false),
            isSolved: (bool) ($row['is_solved'] ?? false),
            viewCount: (int) ($row['view_count'] ?? 0),
            replyCount: (int) ($row['reply_count'] ?? 0),
            lastPostAt: isset($row['last_post_at']) ? new \DateTimeImmutable((string) $row['last_post_at'], new \DateTimeZone('UTC')) : null,
            createdAt: new \DateTimeImmutable((string) ($row['created_at'] ?? 'now'), new \DateTimeZone('UTC')),
            updatedAt: isset($row['updated_at']) ? new \DateTimeImmutable((string) $row['updated_at'], new \DateTimeZone('UTC')) : null,
        );
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        $fmt = 'Y-m-d H:i:s.v';

        return [
            'id' => $this->id,
            'forum_id' => $this->forumId,
            'author_id' => $this->authorId,
            'title' => $this->title,
            'slug' => $this->slug,
            'is_pinned' => $this->isPinned ? 1 : 0,
            'is_locked' => $this->isLocked ? 1 : 0,
            'is_solved' => $this->isSolved ? 1 : 0,
            'view_count' => $this->viewCount,
            'reply_count' => $this->replyCount,
            'last_post_at' => $this->lastPostAt?->format($fmt),
            'created_at' => $this->createdAt->format($fmt),
            'updated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format($fmt),
        ];
    }

    public function rename(string $title): void
    {
        $this->title = $title;
    }

    public function changeSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function moveTo(int $forumId): void
    {
        $this->forumId = $forumId;
    }

    public function setPinned(bool $pinned): void
    {
        $this->isPinned = $pinned;
    }

    public function setLocked(bool $locked): void
    {
        $this->isLocked = $locked;
    }

    public function setSolved(bool $solved): void
    {
        $this->isSolved = $solved;
    }

    /** ثبت پست جدید در موضوع (reply_count بدون پست اول). */
    public function registerPost(\DateTimeImmutable $at, bool $isReply = true): void
    {
        if ($isReply) {
            $this->replyCount++;
        }
        if ($this->lastPostAt === null || $at > $this->lastPostAt) {
            $this->lastPostAt = $at;
        }
    }

    public function unregisterPost(): void
    {
        $this->replyCount = \max(0, $this->replyCount - 1);
    }

    public function registerView(): void
    {
        $this->viewCount++;
    }

    public function applyStats(int $replyCount, ?\DateTimeImmutable $lastPostAt, bool $isSolved): void
    {
        $this->replyCount = \max(0, $replyCount);
        $this->lastPostAt = $lastPostAt;
        $this->isSolved = $isSolved;
    }

    public function acceptsReplies(): bool
    {
        return !$this->isLocked;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function forumId(): int
    {
        return $this->forumId;
    }

    public function authorId(): int
    {
        return $this->authorId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function isSolved(): bool
    {
        return $this->isSolved;
    }

    public function viewCount(): int
    {
        return $this->viewCount;
    }

    public function replyCount(): int
    {
        return $this->replyCount;
    }

    public function lastPostAt(): ?\DateTimeImmutable
    {
        return $this->lastPostAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

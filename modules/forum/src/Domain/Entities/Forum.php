<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * Forum — ریشهٔ Aggregate تالار (DOC-094 §1/§2).
 */

namespace Modules\Forum\Domain\Entities;

/**
 * Forum — تالار/دستهٔ موضوعات با پشتیبانی تو‌در‌تو (parent_id).
 *
 * invariantها:
 * - شمارنده‌های thread_count/post_count/last_post_id با نوشتن‌ها همگام
 *   می‌مانند (PostService + `tvn forum:sync-counters`).
 * - تالار قفل‌شده (is_locked) موضوع جدید نمی‌پذیرد.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class Forum
{
    private function __construct(
        private ?int $id,
        private ?int $parentId,
        private string $title,
        private string $slug,
        private ?string $description,
        private int $position,
        private bool $isLocked,
        private int $threadCount,
        private int $postCount,
        private ?string $lastPostId,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        string $title,
        string $slug,
        ?int $parentId = null,
        ?string $description = null,
        int $position = 0,
    ): self {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            id: null,
            parentId: $parentId,
            title: $title,
            slug: $slug,
            description: $description,
            position: $position,
            isLocked: false,
            threadCount: 0,
            postCount: 0,
            lastPostId: null,
            createdAt: $now,
            updatedAt: null,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            parentId: isset($row['parent_id']) ? (int) $row['parent_id'] : null,
            title: (string) ($row['title'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
            description: isset($row['description']) ? (string) $row['description'] : null,
            position: (int) ($row['position'] ?? 0),
            isLocked: (bool) ($row['is_locked'] ?? false),
            threadCount: (int) ($row['thread_count'] ?? 0),
            postCount: (int) ($row['post_count'] ?? 0),
            lastPostId: isset($row['last_post_id']) ? (string) $row['last_post_id'] : null,
            createdAt: isset($row['created_at']) ? new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')) : null,
            updatedAt: isset($row['updated_at']) ? new \DateTimeImmutable((string) $row['updated_at'], new \DateTimeZone('UTC')) : null,
        );
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        $fmt = 'Y-m-d H:i:s.v';

        return [
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'position' => $this->position,
            'is_locked' => $this->isLocked ? 1 : 0,
            'thread_count' => $this->threadCount,
            'post_count' => $this->postCount,
            'last_post_id' => $this->lastPostId,
            'created_at' => $this->createdAt?->format($fmt),
            'updated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format($fmt),
        ];
    }

    public function revise(string $title, ?string $slug, ?int $parentId, ?string $description, int $position): void
    {
        $this->title = $title;
        if ($slug !== null && $slug !== '') {
            $this->slug = $slug;
        }
        $this->parentId = $parentId;
        $this->description = $description;
        $this->position = $position;
    }

    public function setLocked(bool $locked): void
    {
        $this->isLocked = $locked;
    }

    public function applyStats(int $threadCount, int $postCount, ?string $lastPostId): void
    {
        $this->threadCount = \max(0, $threadCount);
        $this->postCount = \max(0, $postCount);
        $this->lastPostId = $lastPostId;
    }

    public function registerThread(): void
    {
        $this->threadCount++;
    }

    public function registerPost(string $postId): void
    {
        $this->postCount++;
        $this->lastPostId = $postId;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id ??= $id;
    }

    public function parentId(): ?int
    {
        return $this->parentId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function threadCount(): int
    {
        return $this->threadCount;
    }

    public function postCount(): int
    {
        return $this->postCount;
    }

    public function lastPostId(): ?string
    {
        return $this->lastPostId;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}

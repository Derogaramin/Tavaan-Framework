<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * PostRepositoryInterface — پورت ماندگاری پست (Hexagonal).
 */

namespace Modules\Forum\Domain\Repositories;

use Modules\Forum\Domain\Entities\Post;
use Modules\Forum\Domain\Enums\PostStatus;

/**
 * @api 1.0
 */
interface PostRepositoryInterface
{
    public function save(Post $post): void;

    public function findById(string $id): ?Post;

    /** پست اول موضوع (بدنهٔ موضوع — parent_id = null، نخستین created_at). */
    public function firstPostOfThread(string $threadId): ?Post;

    /**
     * صفحه‌بندی پست‌های موضوع — صعودی زمانی؛ solution همیشه نخست.
     *
     * @param list<PostStatus>|null $statuses null = فقط visible
     *
     * @return array{rows: list<Post>, total: int}
     */
    public function paginateForThread(string $threadId, int $page, int $perPage, ?array $statuses = null): array;

    /** همهٔ پست‌های visible یک موضوع (بدون صفحه‌بندی — برای شمارش/برگزیده). */
    public function allVisibleForThread(string $threadId): array;

    /** آخرین پست visible موضوع (برای last_post_at/last_post_id). */
    public function latestVisibleForThread(string $threadId): ?Post;

    /**
     * زمان نوشتن‌های اخیر کاربر (از `since` به بعد، صعودی) — flood control.
     *
     * @return list<\DateTimeImmutable>
     */
    public function recentPostTimesByAuthor(int $authorId, \DateTimeImmutable $since, int $limit = 50): array;

    public function countVisibleForThread(string $threadId): int;

    /** جست‌وجوی fallback — LIKE روی بدنه. */
    public function searchLike(string $query, int $page, int $perPage): array;

    /**
     * آمار پست‌های visible یک تالار (join درون‌ماژولی posts↔threads).
     *
     * @return array{count: int, last_post_id: string|null}
     */
    public function forumPostStats(int $forumId): array;

    /**
     * شناسهٔ همهٔ پست‌های یک موضوع (فارغ از وضعیت) — پاک‌سازی رأی/flag.
     *
     * @return list<string>
     */
    public function idsForThread(string $threadId): array;

    /** حذف سخت همهٔ پست‌های یک موضوع (همراه با حذف موضوع). */
    public function deleteForThread(string $threadId): void;

    public function delete(string $id): void;
}

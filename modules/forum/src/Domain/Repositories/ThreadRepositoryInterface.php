<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ThreadRepositoryInterface — پورت ماندگاری موضوع (Hexagonal).
 */

namespace Modules\Forum\Domain\Repositories;

use Modules\Forum\Domain\Entities\Thread;

/**
 * @api 1.0
 */
interface ThreadRepositoryInterface
{
    public function save(Thread $thread): void;

    public function findById(string $id): ?Thread;

    public function findBySlug(string $slug): ?Thread;

    public function slugExists(string $slug, ?string $exceptId = null): bool;

    /**
     * صفحه‌بندی موضوعات یک مجموعه تالار — pinned نخست، سپس آخرین فعالیت.
     *
     * @param list<int>            $forumIds
     * @param array<string, mixed> $filters    (solved?: bool, search?: string, author_id?: int)
     *
     * @return array{rows: list<Thread>, total: int}
     */
    public function paginate(array $forumIds, array $filters, int $page, int $perPage): array;

    /**
     * آخرین موضوعات فعال (برای صفحهٔ نخست انجمن).
     *
     * @return list<Thread>
     */
    public function latest(int $limit = 10): array;

    /** تعداد موضوعات یک تالار. */
    public function countForForum(int $forumId): int;

    public function incrementViews(string $id): void;

    /** جست‌وجوی fallback — LIKE روی عنوان (وقتی موتور search خالی است). */
    public function searchLike(string $query, int $page, int $perPage): array;

    public function delete(string $id): void;
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ForumRepositoryInterface — پورت ماندگاری تالار (Hexagonal).
 */

namespace Modules\Forum\Domain\Repositories;

use Modules\Forum\Domain\Entities\Forum;

/**
 * @api 1.0
 */
interface ForumRepositoryInterface
{
    public function save(Forum $forum): void;

    public function findById(int $id): ?Forum;

    public function findBySlug(string $slug): ?Forum;

    public function slugExists(string $slug, ?int $exceptId = null): bool;

    /**
     * همهٔ تالارها — به‌ترتیب position/slug (برای درخت فهرست).
     *
     * @return list<Forum>
     */
    public function all(): array;

    /**
     * شناسهٔ تالار + همهٔ نوادگان (بازگشتی، بدون N+1 — روی all()).
     *
     * @return list<int>
     */
    public function withDescendants(int $forumId): array;

    public function delete(int $id): void;
}

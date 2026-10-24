<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * TagRepositoryInterface — پورت ماندگاری برچسب.
 */

namespace Modules\Blog\Domain\Repositories;

use Modules\Blog\Domain\Entities\Tag;

/**
 * @api 1.0
 */
interface TagRepositoryInterface
{
    /** یافتن/ساخت برچسب با slug (firstOrCreate — idempotent). */
    public function findOrCreate(string $name, string $slug): Tag;

    public function findById(int $id): ?Tag;

    public function findBySlug(string $slug): ?Tag;

    /** @return list<Tag> */
    public function all(): array;

    /**
     * برچسب‌های یک مقاله.
     *
     * @return list<Tag>
     */
    public function forPost(string $postId): array;

    /**
     * جایگزینی برچسب‌های مقاله (sync).
     *
     * @param list<int> $tagIds
     */
    public function syncPost(string $postId, array $tagIds): void;

    /** برچسب‌های چند مقاله یک‌جا (بدون N+1). @param list<string> $postIds @return array<string, list<Tag>> */
    public function forPosts(array $postIds): array;
}

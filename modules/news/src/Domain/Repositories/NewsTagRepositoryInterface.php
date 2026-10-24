<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * NewsTagRepositoryInterface — پورت ماندگاری برچسب‌های خبری.
 */

namespace Modules\News\Domain\Repositories;

use Modules\News\Domain\Entities\NewsTag;

/**
 * @api 1.0
 */
interface NewsTagRepositoryInterface
{
    /** یافتن/ساخت با slug (firstOrCreate — idempotent). */
    public function findOrCreate(string $name, string $slug): NewsTag;

    public function findById(int $id): ?NewsTag;

    public function findBySlug(string $slug): ?NewsTag;

    /** @return list<NewsTag> */
    public function all(): array;

    /** @return list<NewsTag> */
    public function forArticle(string $articleId): array;

    /**
     * برچسب‌های چند خبر یک‌جا (بدون N+1).
     *
     * @param list<string> $articleIds
     *
     * @return array<string, list<NewsTag>>
     */
    public function forArticles(array $articleIds): array;

    /**
     * جایگزینی برچسب‌های خبر (sync).
     *
     * @param list<int> $tagIds
     */
    public function syncArticle(string $articleId, array $tagIds): void;
}

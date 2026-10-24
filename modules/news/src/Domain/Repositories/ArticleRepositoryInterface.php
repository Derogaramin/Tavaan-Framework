<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * ArticleRepositoryInterface — پورت ماندگاری خبر.
 */

namespace Modules\News\Domain\Repositories;

use Modules\News\Domain\Entities\Article;

/**
 * پورت مخزن خبر — پیاده‌سازی: DatabaseArticleRepository.
 *
 * @api 1.0
 */
interface ArticleRepositoryInterface
{
    public function save(Article $article): void;

    public function findById(string $id): ?Article;

    public function findBySlug(string $slug, bool $withTrashed = false): ?Article;

    public function slugExists(string $slug, ?string $exceptId = null): bool;

    /**
     * فهرست صفحه‌بندی‌شده با فیلترها.
     *
     * @param array{
     *     status?: string|null,
     *     category_ids?: list<int>|null,
     *     tag_id?: int|null,
     *     author_id?: int|null,
     *     editor_id?: int|null,
     *     breaking?: bool|null,
     *     featured?: bool|null,
     *     published_from?: string|null,
     *     published_to?: string|null,
     *     search?: string|null,
     *     with_trashed?: bool,
     * } $filters
     *
     * @return array{rows: list<Article>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /**
     * آخرین منتشرشده‌ها (فید/sitemap/breaking).
     *
     * @return list<Article>
     */
    public function latestPublished(int $limit, bool $onlyBreaking = false): array;

    /**
     * approvedهای زمان‌بندی‌شده‌ای که موعدشان رسیده است.
     *
     * @return list<Article>
     */
    public function dueScheduled(string $nowUtc, int $limit = 100): array;

    /**
     * اخبار مرتبط (هم‌دسته یا هم‌برچسب) — DOC-092 §6.
     *
     * @return list<Article>
     */
    public function related(Article $article, int $limit = 4): array;

    public function delete(string $id): void;
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * PostRepositoryInterface — پورت ماندگاری مقاله.
 */

namespace Modules\Blog\Domain\Repositories;

use Modules\Blog\Domain\Entities\Post;

/**
 * پورت مخزن مقاله — پیاده‌سازی: DatabasePostRepository (Infrastructure).
 *
 * 🔒 بدون Join/FK بین‌ماژولی (DoD — DOC-113 §3): نویسنده/دسته از راه
 * قراردادها (UserReaderInterface) و شناسه‌ها ترکیب می‌شوند.
 *
 * @api 1.0
 */
interface PostRepositoryInterface
{
    public function save(Post $post): void;

    public function findById(string $id): ?Post;

    public function findBySlug(string $slug, bool $withTrashed = false): ?Post;

    public function slugExists(string $slug, ?string $exceptId = null): bool;

    /**
     * فهرست صفحه‌بندی‌شده با فیلترها (فقط منتشرشده‌ها برای مهمان).
     *
     * @param array{
     *     status?: string|null,
     *     category_id?: int|null,
     *     tag_id?: int|null,
     *     author_id?: int|null,
     *     published_from?: string|null,
     *     published_to?: string|null,
     *     search?: string|null,
     *     with_trashed?: bool,
     * } $filters
     *
     * @return array{rows: list<Post>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /**
     * مقالات زمان‌بندی‌شده‌ای که موعد انتشارشان رسیده است.
     *
     * @return list<Post>
     */
    public function dueScheduled(string $nowUtc, int $limit = 100): array;

    /**
     * آخرین منتشرشده‌ها (برای فید/sitemap).
     *
     * @return list<Post>
     */
    public function latestPublished(int $limit): array;

    public function delete(string $id): void;
}

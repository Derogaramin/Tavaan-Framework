<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * DatabasePostRepository — آداپتر ماندگاری مقاله (DB Facade → blog_posts).
 */

namespace Modules\Blog\Infrastructure\Persistence;

use Modules\Blog\Domain\Entities\Post;
use Modules\Blog\Domain\Repositories\PostRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabasePostRepository — پرس‌وجوی prepared بدون ORM (🔒 ARCH-003: فقط Facade هسته).
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class DatabasePostRepository implements PostRepositoryInterface
{
    public function save(Post $post): void
    {
        $row = $post->toRow();
        $exists = DB::table('blog_posts')->where('id', $post->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('blog_posts')->where('id', $post->id())->update($row);

            return;
        }

        DB::table('blog_posts')->insert($row);
    }

    public function findById(string $id): ?Post
    {
        $row = DB::table('blog_posts')->where('id', $id)->first();

        return $row === null ? null : Post::fromRow($row);
    }

    public function findBySlug(string $slug, bool $withTrashed = false): ?Post
    {
        $query = DB::table('blog_posts')->where('slug', $slug);
        if (!$withTrashed) {
            $query->whereNull('deleted_at');
        }

        $row = $query->first();

        return $row === null ? null : Post::fromRow($row);
    }

    public function slugExists(string $slug, ?string $exceptId = null): bool
    {
        $query = DB::table('blog_posts')->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $base = fn () => $this->filtered($filters);

        $total = $base()->count();
        $rows = $base()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        return [
            'rows' => \array_map(static fn (array $row): Post => Post::fromRow($row), $rows),
            'total' => $total,
        ];
    }

    public function dueScheduled(string $nowUtc, int $limit = 100): array
    {
        $rows = DB::table('blog_posts')
            ->where('status', 'scheduled')
            ->whereNull('deleted_at')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $nowUtc)
            ->orderBy('published_at')
            ->limit(\max(1, $limit))
            ->get();

        return \array_map(static fn (array $row): Post => Post::fromRow($row), $rows);
    }

    public function latestPublished(int $limit): array
    {
        $rows = DB::table('blog_posts')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(\max(1, $limit))
            ->get();

        return \array_map(static fn (array $row): Post => Post::fromRow($row), $rows);
    }

    public function delete(string $id): void
    {
        DB::table('blog_post_tag')->where('post_id', $id)->delete();
        DB::table('blog_posts')->where('id', $id)->delete();
    }

    /**
     * ساخت کوئری فیلترشده (بدون sort/page).
     *
     * @param array<string, mixed> $filters
     */
    private function filtered(array $filters): mixed
    {
        $query = DB::table('blog_posts');

        if ((bool) ($filters['with_trashed'] ?? false)) {
            // بدون فیلتر حذف نرم
        } else {
            $query->whereNull('deleted_at');
        }

        if (isset($filters['status']) && \is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }
        if (isset($filters['author_id'])) {
            $query->where('author_id', (int) $filters['author_id']);
        }
        if (isset($filters['tag_id'])) {
            $tagId = (int) $filters['tag_id'];
            $postIds = DB::table('blog_post_tag')->where('tag_id', $tagId)->pluck('post_id');
            $postIds = \array_values(\array_unique(\array_map('strval', $postIds)));
            if ($postIds === []) {
                $query->where('id', '=', '');   // تهی — بدون نتیجه

                return $query;
            }
            $query->whereIn('id', $postIds);
        }
        if (isset($filters['published_from']) && \is_string($filters['published_from'])) {
            $query->where('published_at', '>=', $filters['published_from']);
        }
        if (isset($filters['published_to']) && \is_string($filters['published_to'])) {
            $query->where('published_at', '<', $filters['published_to']);
        }
        if (isset($filters['search']) && \is_string($filters['search']) && \trim($filters['search']) !== '') {
            $term = \trim($filters['search']);
            $query->where(static function ($q) use ($term): void {
                $q->where('title', 'like', '%' . $term . '%')
                    ->orWhere('excerpt', 'like', '%' . $term . '%');
            });
        }

        return $query;
    }
}

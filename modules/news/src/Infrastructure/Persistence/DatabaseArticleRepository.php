<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * DatabaseArticleRepository — آداپتر ماندگاری خبر (DB Facade → news_articles).
 */

namespace Modules\News\Infrastructure\Persistence;

use Modules\News\Domain\Entities\Article;
use Modules\News\Domain\Repositories\ArticleRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabaseArticleRepository — پرس‌وجوی prepared بدون ORM (🔒 ARCH-003).
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class DatabaseArticleRepository implements ArticleRepositoryInterface
{
    public function save(Article $article): void
    {
        $row = $article->toRow();
        $exists = DB::table('news_articles')->where('id', $article->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('news_articles')->where('id', $article->id())->update($row);

            return;
        }

        DB::table('news_articles')->insert($row);
    }

    public function findById(string $id): ?Article
    {
        $row = DB::table('news_articles')->where('id', $id)->first();

        return $row === null ? null : Article::fromRow($row);
    }

    public function findBySlug(string $slug, bool $withTrashed = false): ?Article
    {
        $query = DB::table('news_articles')->where('slug', $slug);
        if (!$withTrashed) {
            $query->whereNull('deleted_at');
        }

        $row = $query->first();

        return $row === null ? null : Article::fromRow($row);
    }

    public function slugExists(string $slug, ?string $exceptId = null): bool
    {
        $query = DB::table('news_articles')->where('slug', $slug);
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
            'rows' => \array_map(static fn (array $row): Article => Article::fromRow($row), $rows),
            'total' => $total,
        ];
    }

    public function latestPublished(int $limit, bool $onlyBreaking = false): array
    {
        $query = DB::table('news_articles')
            ->where('status', 'published')
            ->whereNull('deleted_at');

        if ($onlyBreaking) {
            $query->where('is_breaking', true);
        }

        $rows = $query->orderByDesc('published_at')->orderByDesc('id')->limit(\max(1, $limit))->get();

        return \array_map(static fn (array $row): Article => Article::fromRow($row), $rows);
    }

    public function dueScheduled(string $nowUtc, int $limit = 100): array
    {
        $rows = DB::table('news_articles')
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $nowUtc)
            ->orderBy('scheduled_at')
            ->limit(\max(1, $limit))
            ->get();

        return \array_map(static fn (array $row): Article => Article::fromRow($row), $rows);
    }

    public function related(Article $article, int $limit = 4): array
    {
        $limit = \max(1, $limit);
        $ids = [];

        // ۱) هم‌برچسب (اولویت بالاتر — یک کوئری join)
        $tagIds = DB::table('news_article_tag')->where('article_id', $article->id())->pluck('tag_id');
        if ($tagIds !== []) {
            $rows = DB::table('news_article_tag as at')
                ->join('news_articles as a', 'a.id', '=', 'at.article_id')
                ->whereIn('at.tag_id', \array_map('intval', $tagIds))
                ->where('at.article_id', '!=', $article->id())
                ->where('a.status', 'published')
                ->whereNull('a.deleted_at')
                ->select('a.id')
                ->limit($limit * 2)
                ->get();
            foreach ($rows as $row) {
                $ids[] = (string) $row['id'];
            }
        }

        // ۲) هم‌دسته (تکمیل ظرفیت)
        if (\count($ids) < $limit && $article->categoryId() !== null) {
            $rows = DB::table('news_articles')
                ->where('category_id', $article->categoryId())
                ->where('id', '!=', $article->id())
                ->where('status', 'published')
                ->whereNull('deleted_at')
                ->select('id')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get();
            foreach ($rows as $row) {
                $ids[] = (string) $row['id'];
            }
        }

        $ids = \array_values(\array_unique(\array_filter($ids, static fn (string $id): bool => $id !== $article->id())));
        if ($ids === []) {
            return [];
        }
        $ids = \array_slice($ids, 0, $limit);

        $rows = DB::table('news_articles')->whereIn('id', $ids)->get();
        $byId = [];
        foreach ($rows as $row) {
            $byId[(string) $row['id']] = Article::fromRow($row);
        }

        // حفظ ترتیب اولویت (هم‌برچسب پیش از هم‌دسته)
        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
        }

        return $out;
    }

    public function delete(string $id): void
    {
        DB::table('news_article_tag')->where('article_id', $id)->delete();
        DB::table('news_articles')->where('id', $id)->delete();
    }

    /**
     * ساخت کوئری فیلترشده (بدون sort/page).
     *
     * @param array<string, mixed> $filters
     */
    private function filtered(array $filters): mixed
    {
        $query = DB::table('news_articles');

        if (!(bool) ($filters['with_trashed'] ?? false)) {
            $query->whereNull('deleted_at');
        }
        if (isset($filters['status']) && \is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['category_ids']) && \is_array($filters['category_ids']) && $filters['category_ids'] !== []) {
            $query->whereIn('category_id', \array_map('intval', $filters['category_ids']));
        }
        if (isset($filters['author_id'])) {
            $query->where('author_id', (int) $filters['author_id']);
        }
        if (isset($filters['editor_id'])) {
            $query->where('editor_id', (int) $filters['editor_id']);
        }
        if (isset($filters['breaking']) && $filters['breaking'] !== null) {
            $query->where('is_breaking', (bool) $filters['breaking']);
        }
        if (isset($filters['featured']) && $filters['featured'] !== null) {
            $query->where('is_featured', (bool) $filters['featured']);
        }
        if (isset($filters['tag_id'])) {
            $tagId = (int) $filters['tag_id'];
            $articleIds = DB::table('news_article_tag')->where('tag_id', $tagId)->pluck('article_id');
            $articleIds = \array_values(\array_unique(\array_map('strval', $articleIds)));
            if ($articleIds === []) {
                $query->where('id', '=', '');   // تهی — بدون نتیجه

                return $query;
            }
            $query->whereIn('id', $articleIds);
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
                    ->orWhere('lead', 'like', '%' . $term . '%');
            });
        }

        return $query;
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * DatabaseNewsTagRepository — آداپتر برچسب‌ها (news_tags + news_article_tag).
 */

namespace Modules\News\Infrastructure\Persistence;

use Modules\News\Domain\Entities\NewsTag;
use Modules\News\Domain\Repositories\NewsTagRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/news/README.md
 */
final class DatabaseNewsTagRepository implements NewsTagRepositoryInterface
{
    public function findOrCreate(string $name, string $slug): NewsTag
    {
        $row = DB::table('news_tags')->where('slug', $slug)->first();
        if ($row !== null) {
            return NewsTag::fromRow($row);
        }

        $id = DB::table('news_tags')->insertGetId(['name' => $name, 'slug' => $slug]);

        return NewsTag::fromRow(['id' => $id, 'name' => $name, 'slug' => $slug]);
    }

    public function findById(int $id): ?NewsTag
    {
        $row = DB::table('news_tags')->where('id', $id)->first();

        return $row === null ? null : NewsTag::fromRow($row);
    }

    public function findBySlug(string $slug): ?NewsTag
    {
        $row = DB::table('news_tags')->where('slug', $slug)->first();

        return $row === null ? null : NewsTag::fromRow($row);
    }

    public function all(): array
    {
        $rows = DB::table('news_tags')->orderBy('name')->get();

        return \array_map(static fn (array $row): NewsTag => NewsTag::fromRow($row), $rows);
    }

    public function forArticle(string $articleId): array
    {
        return $this->forArticles([$articleId])[$articleId] ?? [];
    }

    public function forArticles(array $articleIds): array
    {
        $articleIds = \array_values(\array_unique(\array_map('strval', $articleIds)));
        if ($articleIds === []) {
            return [];
        }

        $rows = DB::table('news_article_tag as at')
            ->join('news_tags as t', 't.id', '=', 'at.tag_id')
            ->whereIn('at.article_id', $articleIds)
            ->select('at.article_id', 't.id', 't.name', 't.slug')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['article_id']][] = NewsTag::fromRow($row);
        }

        return $map;
    }

    public function syncArticle(string $articleId, array $tagIds): void
    {
        DB::table('news_article_tag')->where('article_id', $articleId)->delete();

        foreach (\array_values(\array_unique(\array_map('intval', $tagIds))) as $tagId) {
            DB::table('news_article_tag')->insert(['article_id' => $articleId, 'tag_id' => $tagId]);
        }
    }
}

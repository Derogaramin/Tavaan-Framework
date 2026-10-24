<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * DatabaseTagRepository — آداپتر ماندگاری برچسب (blog_tags + blog_post_tag).
 */

namespace Modules\Blog\Infrastructure\Persistence;

use Modules\Blog\Domain\Entities\Tag;
use Modules\Blog\Domain\Repositories\TagRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/blog/README.md
 */
final class DatabaseTagRepository implements TagRepositoryInterface
{
    public function findOrCreate(string $name, string $slug): Tag
    {
        $row = DB::table('blog_tags')->where('slug', $slug)->first();
        if ($row !== null) {
            return Tag::fromRow($row);
        }

        $id = DB::table('blog_tags')->insertGetId(['name' => $name, 'slug' => $slug]);

        return Tag::fromRow(['id' => $id, 'name' => $name, 'slug' => $slug]);
    }

    public function findById(int $id): ?Tag
    {
        $row = DB::table('blog_tags')->where('id', $id)->first();

        return $row === null ? null : Tag::fromRow($row);
    }

    public function findBySlug(string $slug): ?Tag
    {
        $row = DB::table('blog_tags')->where('slug', $slug)->first();

        return $row === null ? null : Tag::fromRow($row);
    }

    public function all(): array
    {
        $rows = DB::table('blog_tags')->orderBy('name')->get();

        return \array_map(static fn (array $row): Tag => Tag::fromRow($row), $rows);
    }

    public function forPost(string $postId): array
    {
        return $this->forPosts([$postId])[$postId] ?? [];
    }

    public function syncPost(string $postId, array $tagIds): void
    {
        DB::table('blog_post_tag')->where('post_id', $postId)->delete();

        $tagIds = \array_values(\array_unique(\array_map('intval', $tagIds)));
        foreach ($tagIds as $tagId) {
            DB::table('blog_post_tag')->insert(['post_id' => $postId, 'tag_id' => $tagId]);
        }
    }

    public function forPosts(array $postIds): array
    {
        $postIds = \array_values(\array_unique(\array_map('strval', $postIds)));
        if ($postIds === []) {
            return [];
        }

        // یک کوئری join برای همهٔ مقالات — بدون N+1 (DoD §1.5)
        $rows = DB::table('blog_post_tag as pt')
            ->join('blog_tags as t', 't.id', '=', 'pt.tag_id')
            ->whereIn('pt.post_id', $postIds)
            ->select('pt.post_id', 't.id', 't.name', 't.slug')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $postId = (string) $row['post_id'];
            $map[$postId][] = Tag::fromRow($row);
        }

        return $map;
    }
}

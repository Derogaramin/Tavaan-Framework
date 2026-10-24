<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * DatabasePostRepository — آداپتر ماندگاری پست (DB Facade → forum_posts).
 */

namespace Modules\Forum\Infrastructure\Persistence;

use Modules\Forum\Domain\Entities\Post;
use Modules\Forum\Domain\Enums\PostStatus;
use Modules\Forum\Domain\Repositories\PostRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabasePostRepository — پرس‌وجوی prepared بدون ORM (🔒 ARCH-003).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class DatabasePostRepository implements PostRepositoryInterface
{
    public function save(Post $post): void
    {
        $row = $post->toRow();
        $exists = DB::table('forum_posts')->where('id', $post->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('forum_posts')->where('id', $post->id())->update($row);

            return;
        }

        DB::table('forum_posts')->insert($row);
    }

    public function findById(string $id): ?Post
    {
        $row = DB::table('forum_posts')->where('id', $id)->first();

        return $row === null ? null : Post::fromRow($row);
    }

    public function firstPostOfThread(string $threadId): ?Post
    {
        $row = DB::table('forum_posts')
            ->where('thread_id', $threadId)
            ->where('status', '!=', PostStatus::Deleted->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        return $row === null ? null : Post::fromRow($row);
    }

    public function paginateForThread(string $threadId, int $page, int $perPage, ?array $statuses = null): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $values = $statuses === null
            ? [PostStatus::Visible->value]
            : \array_map(static fn (PostStatus $s): string => $s->value, $statuses);

        $base = fn () => DB::table('forum_posts')
            ->where('thread_id', $threadId)
            ->whereIn('status', $values);

        $total = $base()->count();
        $rows = $base()
            ->orderByDesc('is_solution')     // پاسخ برگزیده همیشه نخست
            ->orderBy('created_at')
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get();

        return [
            'rows' => \array_map(static fn (array $row): Post => Post::fromRow($row), $rows),
            'total' => $total,
        ];
    }

    public function allVisibleForThread(string $threadId): array
    {
        $rows = DB::table('forum_posts')
            ->where('thread_id', $threadId)
            ->where('status', PostStatus::Visible->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return \array_map(static fn (array $row): Post => Post::fromRow($row), $rows);
    }

    public function latestVisibleForThread(string $threadId): ?Post
    {
        $row = DB::table('forum_posts')
            ->where('thread_id', $threadId)
            ->where('status', PostStatus::Visible->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $row === null ? null : Post::fromRow($row);
    }

    public function recentPostTimesByAuthor(int $authorId, \DateTimeImmutable $since, int $limit = 50): array
    {
        $rows = DB::table('forum_posts')
            ->select('created_at')
            ->where('author_id', $authorId)
            ->where('created_at', '>=', $since->format('Y-m-d H:i:s.v'))
            ->orderBy('created_at')
            ->limit(\max(1, \min(200, $limit)))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            try {
                $out[] = new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC'));
            } catch (\Throwable) {
                continue;
            }
        }

        return $out;
    }

    public function countVisibleForThread(string $threadId): int
    {
        return DB::table('forum_posts')
            ->where('thread_id', $threadId)
            ->where('status', PostStatus::Visible->value)
            ->count();
    }

    public function searchLike(string $query, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));
        $like = '%' . $query . '%';

        $base = fn () => DB::table('forum_posts')
            ->where('status', PostStatus::Visible->value)
            ->where('body', 'like', $like);

        $total = $base()->count();
        $rows = $base()
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get();

        return [
            'rows' => \array_map(static fn (array $row): Post => Post::fromRow($row), $rows),
            'total' => $total,
        ];
    }

    public function forumPostStats(int $forumId): array
    {
        $row = DB::table('forum_posts', 'p')
            ->join('forum_threads', 'forum_threads.id', '=', 'p.thread_id')
            ->where('forum_threads.forum_id', $forumId)
            ->where('p.status', PostStatus::Visible->value)
            ->selectRaw('COUNT(*) AS cnt')
            ->first();

        $latest = DB::table('forum_posts', 'p')
            ->join('forum_threads', 'forum_threads.id', '=', 'p.thread_id')
            ->where('forum_threads.forum_id', $forumId)
            ->where('p.status', PostStatus::Visible->value)
            ->select('p.id')
            ->orderByDesc('p.created_at')
            ->orderByDesc('p.id')
            ->first();

        return [
            'count' => (int) ($row['cnt'] ?? 0),
            'last_post_id' => isset($latest['id']) ? (string) $latest['id'] : null,
        ];
    }

    public function idsForThread(string $threadId): array
    {
        $rows = DB::table('forum_posts')->select('id')->where('thread_id', $threadId)->get();

        return \array_values(\array_map(static fn (array $row): string => (string) $row['id'], $rows));
    }

    public function deleteForThread(string $threadId): void
    {
        DB::table('forum_posts')->where('thread_id', $threadId)->delete();
    }

    public function delete(string $id): void
    {
        DB::table('forum_posts')->where('id', $id)->delete();
    }
}

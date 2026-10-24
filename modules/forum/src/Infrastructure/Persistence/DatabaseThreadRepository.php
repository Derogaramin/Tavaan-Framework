<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * DatabaseThreadRepository — آداپتر ماندگاری موضوع (DB Facade → forum_threads).
 */

namespace Modules\Forum\Infrastructure\Persistence;

use Modules\Forum\Domain\Entities\Thread;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabaseThreadRepository — پرس‌وجوی prepared بدون ORM (🔒 ARCH-003).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class DatabaseThreadRepository implements ThreadRepositoryInterface
{
    public function save(Thread $thread): void
    {
        $row = $thread->toRow();
        $exists = DB::table('forum_threads')->where('id', $thread->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('forum_threads')->where('id', $thread->id())->update($row);

            return;
        }

        DB::table('forum_threads')->insert($row);
    }

    public function findById(string $id): ?Thread
    {
        $row = DB::table('forum_threads')->where('id', $id)->first();

        return $row === null ? null : Thread::fromRow($row);
    }

    public function findBySlug(string $slug): ?Thread
    {
        $row = DB::table('forum_threads')->where('slug', $slug)->first();

        return $row === null ? null : Thread::fromRow($row);
    }

    public function slugExists(string $slug, ?string $exceptId = null): bool
    {
        $query = DB::table('forum_threads')->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function paginate(array $forumIds, array $filters, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $base = fn () => $this->filtered($forumIds, $filters);

        $total = $base()->count();
        $rows = $base()
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_post_at')
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get();

        return [
            'rows' => \array_map(static fn (array $row): Thread => Thread::fromRow($row), $rows),
            'total' => $total,
        ];
    }

    public function latest(int $limit = 10): array
    {
        $rows = DB::table('forum_threads')
            ->orderByDesc('last_post_at')
            ->orderByDesc('created_at')
            ->limit(\max(1, \min(50, $limit)))
            ->get();

        return \array_map(static fn (array $row): Thread => Thread::fromRow($row), $rows);
    }

    public function countForForum(int $forumId): int
    {
        return DB::table('forum_threads')->where('forum_id', $forumId)->count();
    }

    public function incrementViews(string $id): void
    {
        DB::table('forum_threads')->where('id', $id)->increment('view_count');
    }

    public function searchLike(string $query, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));
        $like = '%' . $query . '%';

        $base = fn () => DB::table('forum_threads')->where('title', 'like', $like);

        $total = $base()->count();
        $rows = $base()
            ->orderByDesc('last_post_at')
            ->forPage($page, $perPage)
            ->get();

        return [
            'rows' => \array_map(static fn (array $row): Thread => Thread::fromRow($row), $rows),
            'total' => $total,
        ];
    }

    public function delete(string $id): void
    {
        DB::table('forum_threads')->where('id', $id)->delete();
    }

    /** @param array<string, mixed> $filters */
    private function filtered(array $forumIds, array $filters)
    {
        $query = DB::table('forum_threads');

        if ($forumIds !== []) {
            $query->whereIn('forum_id', $forumIds);
        }
        if (isset($filters['solved'])) {
            $query->where('is_solved', (bool) $filters['solved'] ? 1 : 0);
        }
        if (isset($filters['author_id'])) {
            $query->where('author_id', (int) $filters['author_id']);
        }
        $search = \trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where('title', 'like', '%' . $search . '%');
        }

        return $query;
    }
}

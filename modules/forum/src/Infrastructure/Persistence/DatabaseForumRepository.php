<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * DatabaseForumRepository — آداپتر ماندگاری تالار (DB Facade → forum_forums).
 */

namespace Modules\Forum\Infrastructure\Persistence;

use Modules\Forum\Domain\Entities\Forum;
use Modules\Forum\Domain\Repositories\ForumRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabaseForumRepository — پرس‌وجوی prepared بدون ORM (🔒 ARCH-003).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class DatabaseForumRepository implements ForumRepositoryInterface
{
    public function save(Forum $forum): void
    {
        $row = $forum->toRow();

        if ($forum->id() !== null) {
            unset($row['id'], $row['created_at']);
            DB::table('forum_forums')->where('id', $forum->id())->update($row);

            return;
        }

        unset($row['id']);
        $id = DB::table('forum_forums')->insertGetId($row);
        $forum->setId((int) $id);
    }

    public function findById(int $id): ?Forum
    {
        $row = DB::table('forum_forums')->where('id', $id)->first();

        return $row === null ? null : Forum::fromRow($row);
    }

    public function findBySlug(string $slug): ?Forum
    {
        $row = DB::table('forum_forums')->where('slug', $slug)->first();

        return $row === null ? null : Forum::fromRow($row);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $query = DB::table('forum_forums')->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function all(): array
    {
        $rows = DB::table('forum_forums')
            ->orderBy('position')
            ->orderBy('title')
            ->get();

        return \array_map(static fn (array $row): Forum => Forum::fromRow($row), $rows);
    }

    public function withDescendants(int $forumId): array
    {
        // یک پرس‌وجو + پیمایش در حافظه (بدون بازگشت DB)
        $all = $this->all();
        $children = [];
        foreach ($all as $forum) {
            $parent = $forum->parentId();
            if ($parent !== null && $forum->id() !== null) {
                $children[$parent][] = $forum->id();
            }
        }

        $out = [$forumId];
        $stack = [$forumId];
        while ($stack !== []) {
            $current = \array_pop($stack);
            foreach ($children[$current] ?? [] as $childId) {
                if (!\in_array($childId, $out, true)) {
                    $out[] = $childId;
                    $stack[] = $childId;
                }
            }
        }

        return $out;
    }

    public function delete(int $id): void
    {
        DB::table('forum_forums')->where('id', $id)->delete();
    }
}

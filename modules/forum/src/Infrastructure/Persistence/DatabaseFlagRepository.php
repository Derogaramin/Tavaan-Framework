<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * DatabaseFlagRepository — آداپتر صف moderation (forum_flags).
 */

namespace Modules\Forum\Infrastructure\Persistence;

use Modules\Forum\Domain\Entities\Flag;
use Modules\Forum\Domain\Enums\FlagStatus;
use Modules\Forum\Domain\Repositories\FlagRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabaseFlagRepository — پرس‌وجوی prepared بدون ORM (🔒 ARCH-003).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class DatabaseFlagRepository implements FlagRepositoryInterface
{
    public function save(Flag $flag): void
    {
        $row = $flag->toRow();

        if ($flag->id() !== null) {
            unset($row['id'], $row['created_at']);
            DB::table('forum_flags')->where('id', $flag->id())->update($row);

            return;
        }

        unset($row['id']);
        $id = DB::table('forum_flags')->insertGetId($row);
        $flag->setId((int) $id);
    }

    public function findById(int $id): ?Flag
    {
        $row = DB::table('forum_flags')->where('id', $id)->first();

        return $row === null ? null : Flag::fromRow($row);
    }

    public function hasOpenByUser(string $postId, int $userId): bool
    {
        return DB::table('forum_flags')
            ->where('post_id', $postId)
            ->where('user_id', $userId)
            ->where('status', FlagStatus::Pending->value)
            ->exists();
    }

    public function countOpenForPost(string $postId): int
    {
        return DB::table('forum_flags')
            ->where('post_id', $postId)
            ->where('status', FlagStatus::Pending->value)
            ->count();
    }

    public function paginate(FlagStatus $status, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $base = fn () => DB::table('forum_flags')->where('status', $status->value);

        $total = $base()->count();
        $rows = $base()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        return [
            'rows' => \array_map(static fn (array $row): Flag => Flag::fromRow($row), $rows),
            'total' => $total,
        ];
    }

    public function countByStatus(FlagStatus $status): int
    {
        return DB::table('forum_flags')->where('status', $status->value)->count();
    }

    public function deleteForPost(string $postId): void
    {
        DB::table('forum_flags')->where('post_id', $postId)->delete();
    }
}

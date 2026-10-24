<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * DatabaseProfileRepository — آداپتر پروفایل انجمن (forum_profiles).
 */

namespace Modules\Forum\Infrastructure\Persistence;

use Modules\Forum\Domain\Entities\ForumProfile;
use Modules\Forum\Domain\Repositories\ProfileRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabaseProfileRepository — یک پروفایل به‌ازای هر کاربر (user_id unique).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class DatabaseProfileRepository implements ProfileRepositoryInterface
{
    public function save(ForumProfile $profile): void
    {
        $row = $profile->toRow();

        if ($profile->id() !== null) {
            unset($row['id'], $row['created_at']);
            DB::table('forum_profiles')->where('id', $profile->id())->update($row);

            return;
        }

        unset($row['id']);
        $id = DB::table('forum_profiles')->insertGetId($row);
        $profile->setId((int) $id);
    }

    public function findByUserId(int $userId): ?ForumProfile
    {
        $row = DB::table('forum_profiles')->where('user_id', $userId)->first();

        return $row === null ? null : ForumProfile::fromRow($row);
    }

    public function findByUsername(string $username): ?ForumProfile
    {
        // case-insensitive — هم‌راستا با Username (ذخیرهٔ اصیل، مقایسهٔ lowercase)
        $row = DB::table('forum_profiles')
            ->whereRaw('LOWER(username) = LOWER(?)', [$username])
            ->first();

        return $row === null ? null : ForumProfile::fromRow($row);
    }

    public function usernameExists(string $username, ?int $exceptUserId = null): bool
    {
        $query = DB::table('forum_profiles')->whereRaw('LOWER(username) = LOWER(?)', [$username]);
        if ($exceptUserId !== null) {
            $query->where('user_id', '!=', $exceptUserId);
        }

        return $query->exists();
    }

    public function findByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = DB::table('forum_profiles')
            ->whereIn('user_id', \array_values(\array_unique(\array_map('intval', $userIds))))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $profile = ForumProfile::fromRow($row);
            $out[(string) $profile->userId()] = $profile;
        }

        return $out;
    }

    public function topByKarma(int $limit = 10): array
    {
        $rows = DB::table('forum_profiles')
            ->where('is_banned', 0)
            ->orderByDesc('karma')
            ->orderByDesc('post_count')
            ->limit(\max(1, \min(100, $limit)))
            ->get();

        return \array_map(static fn (array $row): ForumProfile => ForumProfile::fromRow($row), $rows);
    }

    public function countAll(): int
    {
        return DB::table('forum_profiles')->count();
    }

    public function incrementKarma(int $userId, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        if ($delta > 0) {
            DB::table('forum_profiles')->where('user_id', $userId)->increment('karma', $delta);

            return;
        }

        // کاهش با کف صفر — دو گام (بدون GREATEST برای سازگاری sqlite/mysql)
        $row = DB::table('forum_profiles')->select('karma')->where('user_id', $userId)->first();
        if ($row === null) {
            return;
        }
        $karma = \max(0, (int) $row['karma'] + $delta);
        DB::table('forum_profiles')->where('user_id', $userId)->update(['karma' => $karma]);
    }

    public function delete(int $userId): void
    {
        DB::table('forum_profiles')->where('user_id', $userId)->delete();
    }
}

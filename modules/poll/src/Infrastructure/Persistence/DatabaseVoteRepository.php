<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * DatabaseVoteRepository — آداپتر ماندگاری رأی‌ها روی poll_votes.
 */

namespace Modules\Poll\Infrastructure\Persistence;

use Modules\Poll\Domain\Entities\Vote;
use Modules\Poll\Domain\Repositories\VoteRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabaseVoteRepository — پیاده‌سازی VoteRepositoryInterface (بدون ORM).
 *
 * 🔒 ARCH-003: فقط Facade هسته (DB).
 * شمارش گروهی در PHP انجام می‌شود (بدون تکیه بر GROUP BY در builder).
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
final class DatabaseVoteRepository implements VoteRepositoryInterface
{
    public function add(Vote $vote): void
    {
        DB::table('poll_votes')->insert($vote->toRow());
    }

    public function countsByPoll(string $pollId): array
    {
        $rows = DB::table('poll_votes')
            ->where('poll_id', $pollId)
            ->orderByDesc('id')
            ->limit(100000)
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $index = (int) ($row['option_index'] ?? -1);
            if ($index < 0) {
                continue;
            }
            $counts[$index] = ($counts[$index] ?? 0) + 1;
        }

        return $counts;
    }

    public function countByPoll(string $pollId): int
    {
        return DB::table('poll_votes')->where('poll_id', $pollId)->count();
    }

    public function hasVotedByUser(string $pollId, int $userId): bool
    {
        return DB::table('poll_votes')
            ->where('poll_id', $pollId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function hasVotedByIpHash(string $pollId, string $ipHash): bool
    {
        return DB::table('poll_votes')
            ->where('poll_id', $pollId)
            ->where('ip_hash', $ipHash)
            ->exists();
    }

    public function deleteByPoll(string $pollId): void
    {
        DB::table('poll_votes')->where('poll_id', $pollId)->delete();
    }

    public function countAll(): int
    {
        return DB::table('poll_votes')->count();
    }
}

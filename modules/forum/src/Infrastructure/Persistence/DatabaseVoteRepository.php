<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * DatabaseVoteRepository — آداپتر رأی (DB Facade → forum_votes).
 */

namespace Modules\Forum\Infrastructure\Persistence;

use Modules\Forum\Domain\Repositories\VoteRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabaseVoteRepository — یکتایی (post_id, user_id) با upsert دستی.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class DatabaseVoteRepository implements VoteRepositoryInterface
{
    public function currentVote(string $postId, int $userId): ?int
    {
        $row = DB::table('forum_votes')
            ->where('post_id', $postId)
            ->where('user_id', $userId)
            ->first();

        return $row === null ? null : (int) $row['value'];
    }

    public function cast(string $postId, int $userId, int $value): ?int
    {
        $value = $value >= 0 ? 1 : -1;
        $old = $this->currentVote($postId, $userId);
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');

        if ($old === null) {
            DB::table('forum_votes')->insert([
                'post_id' => $postId,
                'user_id' => $userId,
                'value' => $value,
                'created_at' => $now,
            ]);
        } else {
            DB::table('forum_votes')
                ->where('post_id', $postId)
                ->where('user_id', $userId)
                ->update(['value' => $value]);
        }

        return $old;
    }

    public function remove(string $postId, int $userId): ?int
    {
        $old = $this->currentVote($postId, $userId);
        if ($old !== null) {
            DB::table('forum_votes')
                ->where('post_id', $postId)
                ->where('user_id', $userId)
                ->delete();
        }

        return $old;
    }

    public function scoreOf(string $postId): int
    {
        $rows = DB::table('forum_votes')
            ->select('value')
            ->where('post_id', $postId)
            ->get();

        $score = 0;
        foreach ($rows as $row) {
            $score += (int) $row['value'];
        }

        return $score;
    }

    public function deleteForPost(string $postId): void
    {
        DB::table('forum_votes')->where('post_id', $postId)->delete();
    }
}

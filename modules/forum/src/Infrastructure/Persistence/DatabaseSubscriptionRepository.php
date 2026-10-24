<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * DatabaseSubscriptionRepository — آداپتر اشتراک موضوع (forum_subscriptions).
 */

namespace Modules\Forum\Infrastructure\Persistence;

use Modules\Forum\Domain\Repositories\SubscriptionRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabaseSubscriptionRepository — کلید مرکب (user_id, thread_id)؛ idempotent.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class DatabaseSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function subscribe(int $userId, string $threadId): void
    {
        if ($this->isSubscribed($userId, $threadId)) {
            return;
        }

        DB::table('forum_subscriptions')->insert([
            'user_id' => $userId,
            'thread_id' => $threadId,
            'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function unsubscribe(int $userId, string $threadId): void
    {
        DB::table('forum_subscriptions')
            ->where('user_id', $userId)
            ->where('thread_id', $threadId)
            ->delete();
    }

    public function isSubscribed(int $userId, string $threadId): bool
    {
        return DB::table('forum_subscriptions')
            ->where('user_id', $userId)
            ->where('thread_id', $threadId)
            ->exists();
    }

    public function subscribersOf(string $threadId): array
    {
        $rows = DB::table('forum_subscriptions')
            ->select('user_id')
            ->where('thread_id', $threadId)
            ->get();

        return \array_values(\array_map(static fn (array $row): int => (int) $row['user_id'], $rows));
    }

    public function threadsOf(int $userId, int $limit = 20): array
    {
        $rows = DB::table('forum_subscriptions')
            ->select('thread_id')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(\max(1, \min(100, $limit)))
            ->get();

        return \array_values(\array_map(static fn (array $row): string => (string) $row['thread_id'], $rows));
    }

    public function deleteForThread(string $threadId): void
    {
        DB::table('forum_subscriptions')->where('thread_id', $threadId)->delete();
    }
}

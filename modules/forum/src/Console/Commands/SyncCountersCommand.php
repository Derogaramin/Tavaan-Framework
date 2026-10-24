<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * SyncCountersCommand — `forum:sync-counters` (بازشماری آمار انجمن).
 */

namespace Modules\Forum\Console\Commands;

use Modules\Forum\Application\UseCases\PostService;
use Modules\Forum\Domain\Repositories\ForumRepositoryInterface;
use Modules\Forum\Domain\Repositories\PostRepositoryInterface;
use Modules\Forum\Domain\Repositories\ProfileRepositoryInterface;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Engine\Console\Command;

/**
 * بازشماری شمارنده‌های انجمن (نگهداری):
 *
 * - تالارها: thread_count/post_count/last_post_id
 * - موضوعات: reply_count/last_post_at/is_solved
 * - پروفایل‌ها: thread_count/post_count/solution_count
 *
 * کاربرد: پس از import/مهاجرت داده یا هر ناهمگامی احتمالی.
 * `tvn forum:sync-counters [--dry-run]`
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class SyncCountersCommand extends Command
{
    protected string $signature = 'forum:sync-counters {--dry-run : فقط گزارش، بدون نوشتن}';

    protected string $description = 'بازشماری شمارنده‌های تالار/موضوع/پروفایل انجمن';

    public function handle(): int
    {
        /** @var ForumRepositoryInterface $forums */
        $forums = $this->app->make(ForumRepositoryInterface::class);
        /** @var ThreadRepositoryInterface $threads */
        $threads = $this->app->make(ThreadRepositoryInterface::class);
        /** @var PostRepositoryInterface $posts */
        $posts = $this->app->make(PostRepositoryInterface::class);
        /** @var ProfileRepositoryInterface $profiles */
        $profiles = $this->app->make(ProfileRepositoryInterface::class);
        /** @var PostService $postService */
        $postService = $this->app->make(PostService::class);

        $dryRun = (bool) $this->option('dry-run');

        // ۱) موضوعات: reply_count/last_post_at/is_solved
        $threadRows = DB::table('forum_threads')->select('id')->get();
        $threadCount = 0;
        foreach ($threadRows as $row) {
            $threadId = (string) $row['id'];
            $firstId = $posts->firstPostOfThread($threadId)?->id();
            $visible = $posts->allVisibleForThread($threadId);
            $replies = 0;
            $last = null;
            $solved = false;
            foreach ($visible as $post) {
                if ($post->isSolution()) {
                    $solved = true;
                }
                if ($post->id() === $firstId) {
                    continue;   // پست اول reply نیست
                }
                $replies++;
                $last = $post->createdAt();
            }

            $thread = $threads->findById($threadId);
            if ($thread === null) {
                continue;
            }
            $thread->applyStats($replies, $last, $solved);
            if (!$dryRun) {
                $threads->save($thread);
            }
            $threadCount++;
        }

        // ۲) تالارها
        $forumCount = 0;
        foreach ($forums->all() as $forum) {
            if ($forum->id() !== null && !$dryRun) {
                $postService->refreshForumStatsPublic($forum->id());
            }
            $forumCount++;
        }

        // ۳) پروفایل‌ها
        $profileRows = DB::table('forum_profiles')->get();
        $profileCount = 0;
        foreach ($profileRows as $row) {
            $userId = (int) $row['user_id'];
            $threadRowsOfUser = DB::table('forum_threads')->where('author_id', $userId)->count();
            $postRowsOfUser = DB::table('forum_posts')
                ->where('author_id', $userId)
                ->where('status', '!=', 'deleted')
                ->count();
            $solutions = DB::table('forum_posts')
                ->where('author_id', $userId)
                ->where('is_solution', 1)
                ->where('status', 'visible')
                ->count();

            $profile = $profiles->findByUserId($userId);
            if ($profile === null) {
                continue;
            }
            $profile->applyStats((int) $threadRowsOfUser, (int) $postRowsOfUser, (int) $solutions);
            if (!$dryRun) {
                $profiles->save($profile);
            }
            $profileCount++;
        }

        $this->info(\sprintf(
            '%s %d موضوع، %d تالار، %d پروفایل بازشماری شد.',
            $dryRun ? '(dry-run)' : '✓',
            $threadCount,
            $forumCount,
            $profileCount,
        ));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * PublishScheduledPostsCommand — `blog:publish-scheduled` (DOC-091 §6).
 */

namespace Modules\Blog\Console\Commands;

use Modules\Blog\Application\UseCases\PostWriteService;
use Modules\Blog\Domain\Repositories\PostRepositoryInterface;
use Tavaan\Engine\Console\Command;

/**
 * انتشار مقالات زمان‌بندی‌شدهٔ رسیده — برای cron/schedule:
 * `* * * * * php bin/tvn blog:publish-scheduled`
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class PublishScheduledPostsCommand extends Command
{
    protected string $signature = 'blog:publish-scheduled {--limit=100 : حداکثر مقاله در هر اجرا} {--dry-run : فقط فهرست}';

    protected string $description = 'انتشار مقالات زمان‌بندی‌شده‌ای که موعدشان رسیده است';

    public function handle(): int
    {
        /** @var PostRepositoryInterface $posts */
        $posts = $this->app->make(PostRepositoryInterface::class);
        /** @var PostWriteService $write */
        $write = $this->app->make(PostWriteService::class);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $due = $posts->dueScheduled($now->format('Y-m-d H:i:s.v'), (int) ($this->option('limit') ?? 100));

        if ($due === []) {
            $this->comment('مقالهٔ زمان‌بندی‌شدهٔ رسیده‌ای نیست.');

            return self::SUCCESS;
        }

        $published = 0;
        foreach ($due as $post) {
            if ($this->option('dry-run')) {
                $this->line('  • ' . $post->title() . ' (' . $post->slug() . ') — ' . $post->publishedAt()?->format('Y-m-d H:i'));
                continue;
            }
            $write->publish($post, $now);
            $published++;
        }

        $this->info($this->option('dry-run')
            ? \count($due) . ' مقاله در انتظار انتشار (dry-run).'
            : '✓ ' . $published . ' مقاله منتشر شد.');

        return self::SUCCESS;
    }
}

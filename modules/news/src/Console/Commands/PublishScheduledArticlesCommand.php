<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * PublishScheduledArticlesCommand — `news:publish-scheduled` (DOC-092 §3).
 */

namespace Modules\News\Console\Commands;

use Modules\News\Application\UseCases\ArticleWriteService;
use Modules\News\Domain\Repositories\ArticleRepositoryInterface;
use Tavaan\Engine\Console\Command;

/**
 * انتشار خودکار خبرهای approved با scheduled_at سررسیده.
 *
 * زمان‌بندی: در boot ماژول ثبت می‌شود (`Schedule::command(...)->everyMinute()`)
 * و/یا cron مستقیم: `* * * * * php bin/tvn news:publish-scheduled`.
 * editor_id برای انتشار خودکار = کاربر سیستم (0).
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class PublishScheduledArticlesCommand extends Command
{
    /** شناسهٔ کاربر سیستم برای انتشار خودکار (editor_id). */
    public const SYSTEM_EDITOR_ID = 0;

    protected string $signature = 'news:publish-scheduled {--limit=100 : حداکثر خبر در هر اجرا} {--dry-run : فقط فهرست}';

    protected string $description = 'انتشار خودکار خبرهای زمان‌بندی‌شدهٔ سررسیده (approved + scheduled_at ≤ now)';

    public function handle(): int
    {
        /** @var ArticleRepositoryInterface $articles */
        $articles = $this->app->make(ArticleRepositoryInterface::class);
        /** @var ArticleWriteService $write */
        $write = $this->app->make(ArticleWriteService::class);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $due = $articles->dueScheduled($now->format('Y-m-d H:i:s.v'), (int) ($this->option('limit') ?? 100));

        if ($due === []) {
            $this->comment('خبر زمان‌بندی‌شدهٔ سررسیده‌ای نیست.');

            return self::SUCCESS;
        }

        $published = 0;
        foreach ($due as $article) {
            if ($this->option('dry-run')) {
                $this->line('  • ' . $article->title() . ' (' . $article->slug() . ') — ' . $article->scheduledAt()?->format('Y-m-d H:i'));
                continue;
            }
            $write->publish($article, self::SYSTEM_EDITOR_ID, $article->scheduledAt() ?? $now);
            $published++;
        }

        $this->info($this->option('dry-run')
            ? \count($due) . ' خبر در انتظار انتشار خودکار (dry-run).'
            : '✓ ' . $published . ' خبر طبق زمان‌بندی منتشر شد.');

        return self::SUCCESS;
    }
}

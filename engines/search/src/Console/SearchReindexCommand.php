<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Search\SearchManager;

/**
 * تَوان | Tavaan — Search
 * SearchReindexCommand — بازسازی کامل یک ایندکس از Model (DOC-052).
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class SearchReindexCommand extends Command
{
    protected string $signature = 'search:reindex {index : نام ایندکس} {--chunk=200 : اندازهٔ دسته}';

    protected string $description = 'بازسازی کامل ایندکس جست‌وجو از Model پیکربندی‌شده';

    public function handle(): int
    {
        /** @var SearchManager $search */
        $search = $this->app->make(SearchManager::class);

        $index = (string) $this->argument('index');
        $chunk = \max(1, (int) ($this->option('chunk') ?? 200));

        $this->info('بازسازی ایندکس «' . $index . '»…');
        $started = \microtime(true);

        try {
            $count = $search->reindex($index, $chunk);
        } catch (\Throwable $e) {
            $this->error('خطا: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(\sprintf('✅ %d سند در %.1fs ایندکس شد', $count, \microtime(true) - $started));

        return self::SUCCESS;
    }
}

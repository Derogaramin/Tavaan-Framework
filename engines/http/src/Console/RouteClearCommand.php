<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Console;

use Tavaan\Engine\Console\Command;

/**
 * حذف کش روت‌ها.
 *
 * @api 1.0
 */
final class RouteClearCommand extends Command
{
    protected string $signature = 'route:clear';

    protected string $description = 'حذف فایل کش روت‌ها';

    public function handle(): int
    {
        $file = (string) ($this->app->bound('config') ? $this->app->make('config')->get('router.cache.file', '') : '');
        if ($file !== '' && \is_file($file)) {
            @\unlink($file);
            $this->info('  ✓ routes cache cleared.');

            return self::SUCCESS;
        }

        $this->comment('  کش روتی وجود ندارد.');

        return self::SUCCESS;
    }
}

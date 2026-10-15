<?php

declare(strict_types=1);

namespace Tavaan\Engine\View\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\View\ViewFactory;

/**
 * حذف ویوهای کامپایل‌شده.
 *
 * @api 1.0
 */
final class ViewClearCommand extends Command
{
    protected string $signature = 'view:clear';

    protected string $description = 'حذف کش ویوهای کامپایل‌شده';

    public function handle(): int
    {
        /** @var ViewFactory $views */
        $views = $this->app->make('view');
        $views->flushCompiled();
        $this->info('  ✓ compiled views cleared.');

        return self::SUCCESS;
    }
}

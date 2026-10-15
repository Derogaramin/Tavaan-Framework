<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Testing\TestRunner;

/**
 * اجرای تست‌ها با Native Runner (بدون PHPUnit — DOC-084E).
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class TestCommand extends Command
{
    protected string $signature = 'test {filter? : فیلتر نام تست/کلاس} {--engines : فقط تست موتورها} {--app : فقط تست‌های سراسری}';

    protected string $description = 'اجرای مجموعه تست (Native Runner صفر‌وابستگی)';

    public function handle(): int
    {
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : '';

        $paths = [];
        if (!$this->option('engines')) {
            $paths[] = $base . '/tests/Unit';
            $paths[] = $base . '/tests/Feature';
        }
        if (!$this->option('app')) {
            foreach (\glob($base . '/engines/*/tests') ?: [] as $dir) {
                $paths[] = $dir;
            }
        }

        $filter = $this->argument('filter');

        $bootstrap = $base . '/tests/bootstrap.php';
        if (\is_file($bootstrap)) {
            require_once $bootstrap;
        }

        $runner = new TestRunner($this->output);

        return $runner->run($paths, \is_string($filter) && $filter !== '' ? $filter : null) === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}

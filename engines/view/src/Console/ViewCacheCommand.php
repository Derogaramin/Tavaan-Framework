<?php

declare(strict_types=1);

namespace Tavaan\Engine\View\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\View\ViewFactory;

/**
 * پیش‌کامپایل همهٔ ویوها (AC-VIEW-04: `tvn view:cache`).
 *
 * @api 1.0
 * @see docs/03-engines/view.md
 */
final class ViewCacheCommand extends Command
{
    protected string $signature = 'view:cache';

    protected string $description = 'کامپایل همهٔ قالب‌های .tv.php';

    public function handle(): int
    {
        /** @var ViewFactory $views */
        $views = $this->app->make('view');
        $finder = $views->finder();

        $count = 0;
        $errors = 0;
        foreach ($finder->paths() as $path) {
            if (!\is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || !\str_ends_with($file->getFilename(), '.tv.php')) {
                    continue;
                }

                $name = \str_replace('/', '.', \substr($file->getPathname(), \strlen($path) + 1, -\strlen('.tv.php')));
                try {
                    $views->compileIfNeeded($name, verify: true);
                    $count++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error('  ✗ ' . $name . ': ' . $e->getMessage());
                }
            }
        }

        $this->info(\sprintf('  ✓ %d view(s) compiled%s.', $count, $errors > 0 ? " ({$errors} error(s))" : ''));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}

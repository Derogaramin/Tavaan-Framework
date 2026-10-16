<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Filesystem\FilesystemManager;

/**
 * تَوان | Tavaan — Filesystem
 * StorageLinkCommand — ساخت symlink عمومی `public/storage` (DOC-063 §4: links).
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
final class StorageLinkCommand extends Command
{
    protected string $signature = 'storage:link {--force : بازنشانی linkهای موجود}';

    protected string $description = 'ساخت symlink عمومی برای storage/app/public';

    public function handle(): int
    {
        /** @var FilesystemManager $filesystem */
        $filesystem = $this->app->make(FilesystemManager::class);

        if ($this->option('force')) {
            foreach (\array_keys((array) $this->app->make('config')->get('filesystem.links', [])) as $link) {
                if (\is_link((string) $link)) {
                    @\unlink((string) $link);
                }
            }
        }

        $created = $filesystem->createPublicLinks();

        if ($created === []) {
            $this->info('همهٔ linkها از قبل موجودند.');

            return self::SUCCESS;
        }

        foreach ($created as $link) {
            $this->info('symlink ساخته شد: ' . $link);
        }

        return self::SUCCESS;
    }
}

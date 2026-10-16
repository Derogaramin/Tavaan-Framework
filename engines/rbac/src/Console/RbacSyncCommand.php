<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Rbac\PermissionSync;

/**
 * تَوان | Tavaan — RBAC
 * RbacSyncCommand — همگام‌سازی مجوزها از manifest ماژول‌ها (DOC-057 §7).
 *
 * @api 1.0
 * @see docs/03-engines/rbac.md
 */
final class RbacSyncCommand extends Command
{
    protected string $signature = 'rbac:sync {--module= : فقط یک ماژول}';

    protected string $description = 'همگام‌سازی مجوزهای RBAC از manifest ماژول‌ها';

    public function handle(): int
    {
        /** @var PermissionSync $sync */
        $sync = $this->app->make(PermissionSync::class);
        $basePath = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : '';

        $module = $this->option('module');

        if (\is_string($module) && $module !== '') {
            $manifestPath = $basePath . '/modules/' . $module . '/module.json';
            if (!\is_file($manifestPath)) {
                $this->error('ماژول [' . $module . '] یافت نشد.');

                return self::FAILURE;
            }
        }

        $results = $sync->syncFromModules($basePath);

        if ($results === []) {
            $this->warn('هیچ manifest دارای مجوز یافت نشد.');

            return self::SUCCESS;
        }

        foreach ($results as $moduleId => $result) {
            $this->line(\sprintf(
                '%s: +%d added, -%d removed',
                (string) $moduleId,
                \count($result['added']),
                \count($result['removed']),
            ));
        }

        $this->info('همگام‌سازی مجوزها انجام شد.');

        return self::SUCCESS;
    }
}

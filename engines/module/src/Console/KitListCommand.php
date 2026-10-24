<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Module
 * KitListCommand — `tvn kit:list` فهرست کیت‌های موجود/نصب‌شده.
 */

namespace Tavaan\Engine\Module\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Module\Kit\KitManager;

/**
 * @api 1.0
 * @see docs/05-applications/README.md
 */
final class KitListCommand extends Command
{
    protected string $signature = 'kit:list';

    protected string $description = 'فهرست کیت‌های اپلیکیشنی (kits/) و وضعیت نصب';

    public function handle(): int
    {
        /** @var KitManager $kits */
        $kits = $this->app->make(KitManager::class);

        $discovered = $kits->discover();
        $installed = $kits->installed();

        $seen = [];
        $rows = [];
        foreach ($discovered as $key => $manifest) {
            if ($key !== $manifest->slug || isset($seen[$manifest->slug])) {
                continue;
            }
            $seen[$manifest->slug] = true;

            $state = isset($installed[$manifest->slug])
                ? '✅ نصب‌شده (preset: ' . (string) ($installed[$manifest->slug]['preset'] ?? '?') . ')'
                : '—';

            $rows[] = [
                $manifest->slug,
                $manifest->id,
                $manifest->version,
                $manifest->titleFa,
                \implode(',', \array_column($manifest->modules, 'id')),
                $state,
            ];
        }

        if ($rows === []) {
            $this->comment('کیتی در kits/ یافت نشد.');

            return self::SUCCESS;
        }

        $this->table(
            ['کیت', 'شناسه', 'نسخه', 'عنوان', 'ماژول‌ها', 'وضعیت'],
            $rows,
        );

        return self::SUCCESS;
    }
}

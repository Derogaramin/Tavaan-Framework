<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Upgrade\Upgrader;

/**
 * `tvn upgrade:patches` — فهرست patchها با وضعیت (DOC-081E §7).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class UpgradePatchesCommand extends Command
{
    protected string $signature = 'upgrade:patches';

    protected string $description = 'فهرست data patchها (اجراشده/در انتظار)';

    public function handle(): int
    {
        /** @var Upgrader $upgrader */
        $upgrader = $this->app->make(Upgrader::class);

        $patches = $upgrader->patches();
        if ($patches === []) {
            $this->info('  هیچ patchی در upgrades/ یافت نشد.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($patches as $patch) {
            $rows[] = [$patch['version'], $patch['name'], $patch['applied'] ? '✅ اجراشده' : '⏳ در انتظار'];
        }

        $this->table(['Version', 'Patch', 'Status'], $rows);

        return self::SUCCESS;
    }
}

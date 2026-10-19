<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Backup\BackupManager;
use Tavaan\Engine\Upgrade\Support\BackupAdapter;
use Tavaan\Engine\Upgrade\Support\BackupRunnerInterface;

/**
 * تَوان | Tavaan — Upgrade
 * UpgradeServiceProvider — ثبت Upgrader + پل backup.
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class UpgradeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BackupRunnerInterface::class, static function (ContainerInterface $c): BackupRunnerInterface {
            return new BackupAdapter($c->make(BackupManager::class));
        });

        $this->app->singleton(Upgrader::class, static function (ContainerInterface $c): Upgrader {
            $config = $c->bound('config') ? (array) ($c->make('config')->get('upgrade', []) ?? []) : [];
            $runner = $c->bound(BackupRunnerInterface::class) ? $c->make(BackupRunnerInterface::class) : null;

            return new Upgrader(
                $c,
                $config,
                $runner instanceof BackupRunnerInterface ? $runner : null,
            );
        });

        $this->app->alias(Upgrader::class, 'upgrader');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Upgrader::class, BackupRunnerInterface::class, 'upgrader'];
    }
}

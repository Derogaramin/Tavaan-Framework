<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Backup
 * BackupServiceProvider — ثبت BackupManager به‌عنوان `backup` (Facade: Backup).
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class BackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BackupManager::class, static function (ContainerInterface $c): BackupManager {
            return new BackupManager(
                $c->bound('config') ? $c->make('config') : null,
                $c,
                // 🔒 تنبل: ConnectionManager تا اولین query به PDO وصل نمی‌شود
                $c->bound(ConnectionInterface::class) ? $c->make(ConnectionInterface::class) : null,
                null,   // disk: توسط manager از filesystem resolve می‌شود
                $c->bound(EncrypterInterface::class) ? $c->make(EncrypterInterface::class) : null,
            );
        });
        $this->app->alias(BackupManager::class, 'backup');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [BackupManager::class, 'backup'];
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Core\Contracts\Filesystem\FilesystemInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Filesystem
 * FilesystemServiceProvider — ثبت FilesystemManager به‌عنوان `filesystem` (Facade: Storage).
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
final class FilesystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FilesystemManager::class, static function (ContainerInterface $c): FilesystemManager {
            return new FilesystemManager(
                $c->bound('config') ? $c->make('config') : null,
                $c,
                // 🔒 تنبل: encrypter فقط برای temporaryUrl لازم است
                $c->bound(EncrypterInterface::class) ? $c->make(EncrypterInterface::class) : null,
            );
        });
        $this->app->alias(FilesystemManager::class, 'filesystem');
        $this->app->alias(FilesystemManager::class, FilesystemInterface::class);
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [FilesystemManager::class, FilesystemInterface::class, 'filesystem'];
    }
}

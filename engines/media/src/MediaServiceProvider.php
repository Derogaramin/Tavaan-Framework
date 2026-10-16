<?php

declare(strict_types=1);

namespace Tavaan\Engine\Media;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Filesystem\FilesystemManager;

/**
 * تَوان | Tavaan — Media
 * MediaServiceProvider — ثبت MediaManager به‌عنوان `media` (Facade: Media).
 *
 * @api 1.0
 * @see docs/03-engines/media.md
 */
final class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImageManager::class, static function (ContainerInterface $c): ImageManager {
            $driver = $c->bound('config') ? (string) $c->make('config')->get('media.image.driver', 'auto') : 'auto';

            return new ImageManager($driver);
        });
        $this->app->alias(ImageManager::class, 'media.image');

        $this->app->singleton(MediaManager::class, static function (ContainerInterface $c): MediaManager {
            return new MediaManager(
                $c->bound('config') ? $c->make('config') : null,
                $c,
                $c->bound(FilesystemManager::class) ? $c->make(FilesystemManager::class) : null,
                $c->bound(ConnectionInterface::class) ? $c->make(ConnectionInterface::class) : null,
                $c->bound(ImageManager::class) ? $c->make(ImageManager::class) : null,
            );
        });
        $this->app->alias(MediaManager::class, 'media');
    }

    public function boot(): void
    {
        // درایور پیش‌فرض Image (برای Image::make ایستا)
        if ($this->app->bound('config')) {
            Image::configure((string) $this->app->make('config')->get('media.image.driver', 'auto'));
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [MediaManager::class, ImageManager::class, 'media', 'media.image'];
    }
}

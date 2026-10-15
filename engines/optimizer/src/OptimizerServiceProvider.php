<?php

declare(strict_types=1);

namespace Tavaan\Engine\Optimizer;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Optimizer
 * OptimizerServiceProvider — ثبت Optimizer با cacherهای فعال (config/autoload).
 *
 * Cacherهای route/view/event/container در فازهای بعد (M2+) افزوده می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/optimizer.md
 */
final class OptimizerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Optimizer::class, static function (ContainerInterface $c): Optimizer {
            $base = \defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : \dirname(__DIR__, 3);

            $optimizer = new Optimizer();

            $optimizer->register('config', new ConfigCacher(
                $c->make(\Tavaan\Core\Contracts\Config\ConfigCacheInterface::class),
                (string) ($c->bound('config') ? $c->make('config')->get('config.cache.file', $base . '/storage/cache/config.php') : $base . '/storage/cache/config.php'),
            ));

            $optimizer->register('autoload', new AutoloadCacher($base . '/storage/cache/classmap.php'));

            return $optimizer;
        });
        $this->app->alias(Optimizer::class, 'optimizer');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Optimizer::class, 'optimizer'];
    }
}

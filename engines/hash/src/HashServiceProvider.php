<?php

declare(strict_types=1);

namespace Tavaan\Engine\Hash;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Encryption\HasherInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Hash
 * HashServiceProvider — ثبت HashManager به‌عنوان `hash` (Facade: Hash).
 *
 * @api 1.0
 * @see docs/03-engines/hash.md
 */
final class HashServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HashManager::class, static fn (ContainerInterface $c): HashManager => new HashManager(
            $c->bound('config') ? $c->make('config') : null,
        ));
        $this->app->alias(HashManager::class, 'hash');
        $this->app->alias(HashManager::class, HasherInterface::class);
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [HashManager::class, HasherInterface::class, 'hash'];
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth;

use Tavaan\Core\Context\RequestStack;
use Tavaan\Core\Contracts\Auth\GuardInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Core\Contracts\Encryption\HasherInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Auth
 * AuthServiceProvider — ثبت AuthManager به‌عنوان `auth` (Facade: Auth).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthManager::class, static function (ContainerInterface $c): AuthManager {
            return new AuthManager(
                $c->bound('config') ? $c->make('config') : null,
                $c->bound(ConnectionInterface::class) ? $c->make(ConnectionInterface::class) : null,
                $c->bound(HasherInterface::class) ? $c->make(HasherInterface::class) : null,
                $c->bound(EncrypterInterface::class) ? $c->make(EncrypterInterface::class) : null,
                $c->bound(DispatcherInterface::class) ? $c->make(DispatcherInterface::class) : null,
                $c->bound('cache') ? $c->make('cache') : null,
                $c->bound(RequestStack::class) ? $c->make(RequestStack::class) : null,
            );
        });
        $this->app->alias(AuthManager::class, 'auth');

        // GuardInterface → guard پیش‌فرض
        $this->app->bind(GuardInterface::class, static fn (ContainerInterface $c): GuardInterface => $c->make('auth')->guard());
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [AuthManager::class, GuardInterface::class, 'auth'];
    }
}

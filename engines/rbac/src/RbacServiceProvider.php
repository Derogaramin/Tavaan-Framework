<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Authorization\GateInterface;
use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Auth\AuthManager;

/**
 * تَوان | Tavaan — RBAC
 * RbacServiceProvider — ثبت Gate/RoleManager/PermissionResolver (Facade: Gate).
 *
 * @api 1.0
 * @see docs/03-engines/rbac.md
 */
final class RbacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionResolver::class, static function (ContainerInterface $c): PermissionResolver {
            return new PermissionResolver(
                $c->make(ConnectionInterface::class),
                $c->bound('cache') ? $c->make('cache') : null,
                (array) ($c->bound('config') ? $c->make('config')->get('rbac', []) : []),
                $c->bound(DispatcherInterface::class) ? $c->make(DispatcherInterface::class) : null,
            );
        });

        $this->app->singleton(RoleManager::class, static function (ContainerInterface $c): RoleManager {
            return new RoleManager(
                $c->make(ConnectionInterface::class),
                $c->make(PermissionResolver::class),
                (array) ($c->bound('config') ? $c->make('config')->get('rbac', []) : []),
                $c->bound(DispatcherInterface::class) ? $c->make(DispatcherInterface::class) : null,
            );
        });

        $this->app->singleton(Gate::class, static function (ContainerInterface $c): Gate {
            $userResolver = static function () use ($c): ?Authenticatable {
                if (!$c->bound('auth')) {
                    return null;
                }
                $auth = $c->make('auth');
                if (!$auth instanceof AuthManager) {
                    return null;
                }
                $user = $auth->user();

                return $user instanceof Authenticatable ? $user : null;
            };

            return new Gate(
                $c->make(PermissionResolver::class),
                $userResolver,
                (array) ($c->bound('config') ? $c->make('config')->get('rbac', []) : []),
            );
        });
        $this->app->alias(Gate::class, 'gate');
        $this->app->alias(Gate::class, GateInterface::class);

        $this->app->singleton(PermissionSync::class, static fn (ContainerInterface $c): PermissionSync => new PermissionSync(
            $c->make(ConnectionInterface::class),
            $c->make(RoleManager::class),
            (array) ($c->bound('config') ? $c->make('config')->get('rbac', []) : []),
            $c->bound(DispatcherInterface::class) ? $c->make(DispatcherInterface::class) : null,
        ));
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Gate::class, GateInterface::class, RoleManager::class, PermissionResolver::class, PermissionSync::class, 'gate'];
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Authorization\GateInterface;
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

            $config = (array) ($c->bound('config') ? $c->make('config')->get('rbac', []) : []);
            $gate = new Gate(
                $c->make(PermissionResolver::class),
                $userResolver,
                $config,
            );

            // policies اعلامی از config (rbac.policies) — ماژول‌ها به‌جای
            // Gate::policy در boot، نگاشت را در config خود ادغام می‌کنند (M9).
            foreach ((array) ($config['policies'] ?? []) as $model => $policy) {
                if (\is_string($model) && \is_string($policy) && $model !== '' && $policy !== '') {
                    $gate->policy($model, $policy);
                }
            }

            return $gate;
        });
        $this->app->alias(Gate::class, 'gate');
        $this->app->alias(Gate::class, GateInterface::class);

        // aliasهای RoleManager/PermissionSync/PermissionResolver — نقطهٔ ورود
        // ماژول‌ها/کیت‌ها بدون ارجاع به کلاس موتور (ARCH-003، M9).
        $this->app->alias(RoleManager::class, 'rbac.roles');
        $this->app->alias(PermissionResolver::class, 'rbac.permissions');
        $this->app->alias(PermissionSync::class, 'rbac.sync');

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

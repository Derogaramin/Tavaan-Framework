<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade کنترل دسترسی (Gate).
 *
 * @method static bool allows(string $ability, mixed ...$arguments)
 * @method static bool denies(string $ability, mixed ...$arguments)
 * @method static bool check(iterable|string $abilities, mixed ...$arguments)
 * @method static bool any(iterable|string $abilities, mixed ...$arguments)
 * @method static \Tavaan\Core\Contracts\Authorization\ResponseInterface authorize(string $ability, mixed ...$arguments)
 * @method static \Tavaan\Engine\Rbac\Gate forUser(mixed $user)
 * @method static \Tavaan\Engine\Rbac\Gate define(string $ability, callable|string $callback)
 * @method static \Tavaan\Engine\Rbac\Gate policy(string $model, string|object $policy)
 * @method static \Tavaan\Engine\Rbac\Gate before(callable $callback)
 * @method static \Tavaan\Engine\Rbac\Gate after(callable $callback)
 * @method static ?object getPolicyFor(object|string $class)
 * @mixin \Tavaan\Engine\Rbac\Gate
 * @api 1.0
 * @see docs/03-engines/rbac.md
 */
final class Gate extends Facade
{
    protected static function accessor(): string
    {
        return 'gate';
    }
}

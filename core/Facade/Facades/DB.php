<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;


/**
 * Facade دیتابیس (DB).
 *
 * @method static \Tavaan\Engine\Database\Connection connection(?string $name = null)
 * @method static \Tavaan\Engine\Database\QueryBuilder table(string $table, ?string $as = null)
 * @method static \Tavaan\Engine\Database\QueryBuilder query()
 * @method static \Tavaan\Engine\Database\Expression raw(string $value, array $bindings = [])
 * @method static array select(string $sql, array $bindings = [])
 * @method static bool statement(string $sql, array $bindings = [])
 * @method static mixed transaction(\Closure $callback, int $attempts = 1, ?string $isolation = null)
 * @mixin \Tavaan\Engine\Database\ConnectionManager
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class DB extends Facade
{
    protected static function accessor(): string
    {
        return 'db';
    }
}

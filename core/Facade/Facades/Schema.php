<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;


/**
 * Facade ساختار دیتابیس (Schema).
 *
 * @method static void create(string $table, \Closure $callback)
 * @method static void table(string $table, \Closure $callback)
 * @method static void drop(string $table)
 * @method static void dropIfExists(string $table)
 * @method static void rename(string $from, string $to)
 * @method static bool hasTable(string $table)
 * @method static bool hasColumn(string $table, string $column)
 * @method static array getColumns(string $table)
 * @method static array getTables()
 * @method static ?string getColumnType(string $table, string $column)
 * @method static array introspect(string $table)
 * @method static \Tavaan\Engine\Database\Schema\SchemaBuilder connection(?string $name = null)
 * @mixin \Tavaan\Engine\Database\Schema\SchemaBuilder
 * @api 1.0
 * @see docs/03-engines/schema.md
 */
final class Schema extends Facade
{
    protected static function accessor(): string
    {
        return 'schema';
    }
}

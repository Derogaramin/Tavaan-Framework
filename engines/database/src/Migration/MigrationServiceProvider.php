<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Migration;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Database\Connection;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Database\Schema\SchemaBuilder;

/**
 * تَوان | Tavaan — Migration
 * MigrationServiceProvider — ثبت Migrator (بدون Facade — فقط CLI — DOC-049).
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final class MigrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MigrationRepository::class, static fn (ContainerInterface $c): MigrationRepository => new MigrationRepository(self::connection($c)));

        $this->app->singleton(Migrator::class, static function (ContainerInterface $c): Migrator {
            $basePath = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : '';
            $env = (string) ($c->bound('config') ? $c->make('config')->get('app.env', 'production') : 'production');

            return new Migrator(self::connection($c), $c->make(SchemaBuilder::class), $basePath, $env);
        });
    }

    private static function connection(ContainerInterface $c): Connection
    {
        $manager = $c->make(ConnectionManager::class);
        $connection = $manager->connection();

        if (!$connection instanceof Connection) {
            throw new \LogicException('Migration requires a full Connection.');
        }

        return $connection;
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Migrator::class, MigrationRepository::class];
    }
}

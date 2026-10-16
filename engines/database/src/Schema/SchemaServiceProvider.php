<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Schema;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\SchemaBuilderInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Database\ConnectionManager;

/**
 * تَوان | Tavaan — Schema
 * SchemaServiceProvider — ثبت SchemaBuilder به‌عنوان `schema` (Facade: Schema).
 *
 * @api 1.0
 * @see docs/03-engines/schema.md
 */
final class SchemaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SchemaBuilder::class, static fn (ContainerInterface $c): SchemaBuilder => new SchemaBuilder($c->make(ConnectionManager::class)));
        $this->app->alias(SchemaBuilder::class, 'schema');
        $this->app->alias(SchemaBuilder::class, SchemaBuilderInterface::class);
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [SchemaBuilder::class, SchemaBuilderInterface::class, 'schema'];
    }
}

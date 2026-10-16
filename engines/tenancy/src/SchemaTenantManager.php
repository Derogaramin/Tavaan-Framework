<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Tenancy\TenantInterface;
use Tavaan\Engine\Tenancy\Events\TenantConnected;
use Tavaan\Engine\Tenancy\Exceptions\TenancyException;

/**
 * تَوان | Tavaan — Tenancy
 * SchemaTenantManager — استراتژی C: schema به ازای tenant (PostgreSQL).
 *
 * `SET search_path TO tenant_{identifier}, public` روی اتصال جاری.
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class SchemaTenantManager implements TenantStrategyInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?ConfigRepository $config = null,
        private readonly ?DispatcherInterface $events = null,
    ) {
    }

    public function name(): string
    {
        return 'schema';
    }

    public function connect(TenantInterface $tenant): void
    {
        $connection = $this->connection();
        $schema = $this->schemaName($tenant);

        if ($connection->driver() !== 'pgsql') {
            throw TenancyException::for('tenancy.schema_requires_pgsql', ['driver' => $connection->driver()]);
        }

        $connection->statement('SET search_path TO "' . $schema . '", public');
        $this->events?->dispatch(new TenantConnected($tenant->id(), 'schema'));
    }

    public function disconnect(): void
    {
        try {
            $connection = $this->connection();
            if ($connection->driver() === 'pgsql') {
                $connection->statement('SET search_path TO public');
            }
        } catch (\Throwable) {
            // پاک‌سازی best-effort
        }
    }

    public function provision(TenantInterface $tenant): void
    {
        $connection = $this->connection();
        if ($connection->driver() !== 'pgsql') {
            throw TenancyException::for('tenancy.schema_requires_pgsql', ['driver' => $connection->driver()]);
        }
        $schema = $this->schemaName($tenant);
        $connection->statement('CREATE SCHEMA IF NOT EXISTS "' . $schema . '"');
    }

    public function deprovision(TenantInterface $tenant): void
    {
        $connection = $this->connection();
        if ($connection->driver() !== 'pgsql') {
            return;
        }
        $connection->statement('DROP SCHEMA IF EXISTS "' . $this->schemaName($tenant) . '" CASCADE');
    }

    /** نام schema با prefix پیکربندی‌شده. */
    public function schemaName(TenantInterface $tenant): string
    {
        $prefix = (string) ($this->config?->get('tenancy.schema_prefix', 'tenant_') ?? 'tenant_');
        $identifier = \preg_replace('/[^A-Za-z0-9_]/', '', $tenant->identifier());
        if ($identifier === '') {
            throw TenancyException::for('tenancy.unsafe_identifier', ['identifier' => $tenant->identifier()]);
        }

        return $prefix . $identifier;
    }

    private function connection(): \Tavaan\Engine\Database\Connection
    {
        if (!$this->container->bound('db')) {
            throw TenancyException::for('tenancy.database_required');
        }
        /** @var \Tavaan\Engine\Database\ConnectionManager $manager */
        $manager = $this->container->make('db');
        $central = (string) ($this->config?->get('tenancy.central_connection', '') ?? '');

        return $manager->connection($central !== '' ? $central : null);
    }
}

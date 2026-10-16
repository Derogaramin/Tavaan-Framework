<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Tenancy\TenantInterface;
use Tavaan\Engine\Tenancy\Exceptions\TenancyException;

/**
 * تَوان | Tavaan — Tenancy
 * TenantProvisioner — ساخت/مهاجرت/حذف فضای دادهٔ tenantها (DOC-028 §3.2).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantProvisioner
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly TenantManager $tenants,
    ) {
    }

    /**
     * ساخت کامل tenant: رکورد مرکزی + فضای داده (استراتژی B/C) + migrate.
     *
     * @param array<string, mixed>     $data
     * @param callable(TenantInterface): void|null $seed
     */
    public function create(array $data, ?callable $seed = null, bool $migrate = true): TenantInterface
    {
        $tenant = $this->tenants->create($data);

        $strategy = $this->tenants->strategy();
        if ($strategy !== 'shared') {
            $manager = $this->strategyManager();
            if ($manager !== null) {
                $manager->provision($tenant);
                if ($migrate) {
                    $this->migrateTenant($tenant);
                }
            }
        }

        if ($seed !== null) {
            $this->tenants->run($tenant, static function () use ($seed, $tenant): void {
                $seed($tenant);
            });
        }

        return $tenant;
    }

    /** اجرای migrationها برای یک tenant (در context خودش). */
    public function migrateTenant(TenantInterface $tenant): int
    {
        return $this->tenants->run($tenant, function (): int {
            if (!$this->container->bound('console.kernel')) {
                throw TenancyException::for('tenancy.console_required');
            }
            $console = $this->container->make('console.kernel');

            return (int) $console->call('migrate');
        });
    }

    /**
     * مهاجرت همهٔ tenantهای فعال — با Rate Limit و جمع‌آوری خطاها (Resume-able).
     *
     * @return array{migrated: int, failed: list<array{identifier: string, error: string}>}
     */
    public function migrateAll(int $sleepMicros = 50_000, ?callable $progress = null): array
    {
        $migrated = 0;
        $failed = [];

        foreach ($this->tenants->active() as $tenant) {
            try {
                $this->migrateTenant($tenant);
                $migrated++;
            } catch (\Throwable $e) {
                $failed[] = ['identifier' => $tenant->identifier(), 'error' => $e->getMessage()];
            }

            if ($progress !== null) {
                $progress($tenant, $migrated, $failed);
            }
            if ($sleepMicros > 0) {
                \usleep($sleepMicros);   // rate limit
            }
        }

        return ['migrated' => $migrated, 'failed' => $failed];
    }

    /**
     * حذف tenant: archive (غیرفعال) و در صورت force حذف فضای داده.
     */
    public function delete(TenantInterface|string|int $tenant, bool $force = false): bool
    {
        $entity = $tenant instanceof TenantInterface
            ? $tenant
            : ($this->tenants->findByIdentifier((string) $tenant) ?? $this->tenants->find($tenant));

        if ($entity === null) {
            return false;
        }

        $this->tenants->deactivate($entity);

        if ($force) {
            if ($this->tenants->strategy() !== 'shared') {
                $this->strategyManager()?->deprovision($entity);
            }
            $this->tenants->delete($entity);
        }

        return true;
    }

    private function strategyManager(): ?TenantStrategyInterface
    {
        $binding = match ($this->tenants->strategy()) {
            'database' => DatabaseTenantManager::class,
            'schema' => SchemaTenantManager::class,
            default => null,
        };
        if ($binding === null || !$this->container->bound($binding)) {
            return null;
        }
        $manager = $this->container->make($binding);

        return $manager instanceof TenantStrategyInterface ? $manager : null;
    }
}

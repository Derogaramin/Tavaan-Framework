<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Tenancy\TenantManager;
use Tavaan\Engine\Tenancy\TenantProvisioner;

/**
 * تَوان | Tavaan — Tenancy
 * TenantMigrateCommand — اجرای migration روی tenantها (DOC-028 §3.2).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantMigrateCommand extends Command
{
    protected string $signature = 'tenant:migrate {--tenant= : فقط یک tenant (identifier)} {--sleep=50 : مکث بین tenantها (ms)}';

    protected string $description = 'اجرای migrationها روی همهٔ tenantها (با Rate Limit)';

    public function handle(): int
    {
        /** @var TenantProvisioner $provisioner */
        $provisioner = $this->app->make('tenant.provisioner');
        /** @var TenantManager $tenants */
        $tenants = $this->app->make('tenant');

        $only = $this->option('tenant');
        if (\is_string($only) && $only !== '') {
            $tenant = $tenants->findByIdentifier($only);
            if ($tenant === null) {
                $this->error('tenant «' . $only . '» یافت نشد');

                return self::FAILURE;
            }
            $this->info('migrate روی «' . $only . '»…');
            try {
                $provisioner->migrateTenant($tenant);
                $this->info('✅ انجام شد');

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error('خطا: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        $sleepMs = \max(0, (int) ($this->option('sleep') ?? 50));
        $this->info('migrate روی همهٔ tenantهای فعال…');
        $result = $provisioner->migrateAll(
            $sleepMs * 1000,
            function (object $tenant): void {
                $this->comment('  • ' . $tenant->identifier());
            },
        );

        $this->info(\sprintf('✅ %d tenant مهاجرت شد', $result['migrated']));
        if ($result['failed'] !== []) {
            $this->error(\sprintf('❌ %d مورد ناموفق:', \count($result['failed'])));
            foreach ($result['failed'] as $failure) {
                $this->line('  - ' . $failure['identifier'] . ': ' . $failure['error']);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

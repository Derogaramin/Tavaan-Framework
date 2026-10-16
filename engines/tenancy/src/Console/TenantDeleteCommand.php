<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Tenancy\TenantManager;
use Tavaan\Engine\Tenancy\TenantProvisioner;

/**
 * تَوان | Tavaan — Tenancy
 * TenantDeleteCommand — archive یا حذف کامل tenant (DOC-028 §3.2).
 *
 * 🔒 حذف کامل (drop فضای داده) فقط با --force و تأیید دوم.
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantDeleteCommand extends Command
{
    protected string $signature = 'tenant:delete {identifier : شناسهٔ tenant} {--force : حذف کامل رکورد + فضای داده} {--yes : بدون تأیید}';

    protected string $description = 'غیرفعال‌سازی (archive) یا حذف کامل tenant';

    public function handle(): int
    {
        /** @var TenantManager $tenants */
        $tenants = $this->app->make('tenant');
        /** @var TenantProvisioner $provisioner */
        $provisioner = $this->app->make('tenant.provisioner');

        $identifier = (string) $this->argument('identifier');
        $tenant = $tenants->findByIdentifier($identifier);
        if ($tenant === null) {
            $this->error('tenant «' . $identifier . '» یافت نشد');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $yes = (bool) $this->option('yes');

        if ($force && !$yes) {
            $this->warn('⚠️ حذف کامل: رکورد مرکزی + فضای دادهٔ «' . $identifier . '» از بین می‌رود.');
            $this->warn('برای ادامه، دستور را با --yes تکرار کنید (تأیید دوگانه).');

            return self::FAILURE;
        }

        try {
            $provisioner->delete($tenant, $force);
        } catch (\Throwable $e) {
            $this->error('حذف ناموفق: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info($force
            ? '🗑 tenant «' . $identifier . '» کاملاً حذف شد'
            : '📦 tenant «' . $identifier . '» غیرفعال (archive) شد');

        return self::SUCCESS;
    }
}

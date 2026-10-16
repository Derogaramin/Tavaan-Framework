<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Tenancy\TenantManager;

/**
 * تَوان | Tavaan — Tenancy
 * DbTenantGuardCommand — گزارش کوئری‌های بدون Scope (TEN-SEC-01).
 *
 * Query Log فرایند جاری را بررسی می‌کند: هر SELECT/UPDATE/DELETE روی
 * جدول tenant-aware که `tenant_id` در WHERE نداشته باشد → تخلف.
 *
 * 💡 برای استفادهٔ مؤثر: query_log را فعال کنید، کد هدف را در همان
 * فرایند اجرا کنید (مثلاً از طریق schedule یا تست) سپس این دستور را
 * فراخوانی کنید — یا در تست‌ها مستقیماً TenantQueryGuard::scan() را
 * با فهرست کوئری‌ها صدا بزنید.
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class DbTenantGuardCommand extends Command
{
    protected string $signature = 'db:tenant-guard {--tables= : جداول tenant-aware (کاما-جدا؛ خالی = همه جز ignore)}';

    protected string $description = 'یافتن کوئری‌های بدون tenant_id روی جداول tenant-aware';

    public function handle(): int
    {
        /** @var TenantManager $tenants */
        $tenants = $this->app->make('tenant');

        $queries = [];
        if ($this->app->bound('db')) {
            try {
                $connection = $this->app->make('db')->connection();
                $queries = (array) $connection->queryLog();
            } catch (\Throwable) {
                $queries = [];
            }
        }

        $tablesOption = $this->option('tables');
        $tenantTables = \is_string($tablesOption) && \trim($tablesOption) !== ''
            ? \array_map(\trim(...), \explode(',', $tablesOption))
            : null;

        $violations = TenantQueryGuard::scan($queries, $tenants->ignoreTables(), $tenantTables, $tenants->tenantColumn());

        if ($violations === []) {
            $this->info('✅ ' . \count($queries) . ' کوئری بررسی شد — هیچ کوئری بدون scope یافت نشد.');

            return self::SUCCESS;
        }

        $this->error(\sprintf('❌ %d کوئری بدون %s روی جدول tenant-aware:', \count($violations), $tenants->tenantColumn()));
        foreach ($violations as $v) {
            $this->line('  • [' . $v['table'] . '] ' . $v['sql']);
        }

        return self::FAILURE;
    }
}

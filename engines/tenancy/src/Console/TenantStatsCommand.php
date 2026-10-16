<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Tenancy\TenantManager;

/**
 * تَوان | Tavaan — Tenancy
 * TenantStatsCommand — آمار مصرف منابع tenantها (DOC-028 §3.2).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantStatsCommand extends Command
{
    protected string $signature = 'tenant:stats {identifier? : فقط یک tenant}';

    protected string $description = 'گزارش وضعیت و مصرف منابع tenantها';

    public function handle(): int
    {
        /** @var TenantManager $tenants */
        $tenants = $this->app->make('tenant');

        $only = $this->argument('identifier');
        if (\is_string($only) && $only !== '') {
            $tenant = $tenants->findByIdentifier($only);
            if ($tenant === null) {
                $this->error('tenant «' . $only . '» یافت نشد');

                return self::FAILURE;
            }
            $list = [$tenant];
        } else {
            $list = $tenants->all();
        }

        if ($list === []) {
            $this->comment('هیچ tenantی ثبت نشده است.');

            return self::SUCCESS;
        }

        $strategy = $tenants->strategy();
        $this->info('استراتژی: ' . $strategy . ' | تعداد: ' . \count($list));

        $quota = null;
        if ($this->app->bound('tenant.quota')) {
            $quota = $this->app->make('tenant.quota');
        }

        foreach ($list as $tenant) {
            $this->line('');
            $this->info('▸ ' . $tenant->identifier() . ' (id=' . $tenant->id() . ', plan=' . $tenant->plan() . ', active=' . ($tenant->isActive() ? 'yes' : 'no') . ')');
            if ($strategy === 'database') {
                $this->comment('    database: ' . $tenant->databaseName());
            }
            $quotas = method_exists($tenant, 'quotas') ? (array) $tenant->quotas() : [];
            foreach ($quotas as $resource => $spec) {
                $used = $quota !== null && method_exists($quota, 'usage') ? $quota->usage((string) $resource, $tenant) : '—';
                $limit = is_array($spec) ? ($spec['limit'] ?? '∞') : '∞';
                $period = is_array($spec) ? ($spec['period'] ?? 'total') : 'total';
                $this->line('    quota ' . $resource . ': ' . $used . '/' . $limit . ' (' . $period . ')');
            }
            if ($quotas === []) {
                $this->comment('    quota: بدون محدودیت');
            }
        }

        return self::SUCCESS;
    }
}

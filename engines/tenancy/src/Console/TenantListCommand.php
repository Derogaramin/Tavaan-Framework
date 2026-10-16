<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Tenancy\TenantManager;

/**
 * تَوان | Tavaan — Tenancy
 * TenantListCommand — فهرست tenantها (DOC-028 §3.2).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantListCommand extends Command
{
    protected string $signature = 'tenant:list {--limit=200 : حداکثر رکورد}';

    protected string $description = 'فهرست tenantهای ثبت‌شده';

    public function handle(): int
    {
        /** @var TenantManager $tenants */
        $tenants = $this->app->make('tenant');
        $limit = \max(1, (int) ($this->option('limit') ?? 200));

        $all = $tenants->all($limit);
        if ($all === []) {
            $this->comment('هیچ tenantی ثبت نشده است.');

            return self::SUCCESS;
        }

        $this->info(\sprintf('%-4s %-20s %-24s %-8s %-6s %s', 'ID', 'IDENTIFIER', 'DOMAIN', 'PLAN', 'ACTIVE', 'NAME'));
        foreach ($all as $tenant) {
            $this->line(\sprintf(
                '%-4s %-20s %-24s %-8s %-6s %s',
                (string) $tenant->id(),
                $tenant->identifier(),
                (string) ($tenant->domain() ?? '—'),
                $tenant->plan(),
                $tenant->isActive() ? 'yes' : 'NO',
                $tenant->name(),
            ));
        }
        $this->comment(\sprintf('مجموع: %d (فعال: %d)', \count($all), $tenants->countActive()));

        return self::SUCCESS;
    }
}

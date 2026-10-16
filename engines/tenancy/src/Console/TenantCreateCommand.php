<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Tenancy\TenantProvisioner;

/**
 * تَوان | Tavaan — Tenancy
 * TenantCreateCommand — ساخت tenant + provisioning (DOC-028 §3.2).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantCreateCommand extends Command
{
    protected string $signature = 'tenant:create {identifier : شناسهٔ یکتا} {--name= : نام نمایشی} {--domain= : دامنه} {--plan=free : پلن} {--settings= : JSON تنظیمات} {--quota= : JSON سهمیه‌ها} {--no-migrate : بدون اجرای migration}';

    protected string $description = 'ساخت tenant جدید (رکورد مرکزی + فضای داده)';

    public function handle(): int
    {
        /** @var TenantProvisioner $provisioner */
        $provisioner = $this->app->make('tenant.provisioner');

        $identifier = (string) $this->argument('identifier');
        $settings = $this->decodeJsonOption('settings');
        $quota = $this->decodeJsonOption('quota');
        if ($settings === false || $quota === false) {
            $this->error('گزینه‌های --settings و --quota باید JSON معتبر باشند');

            return self::FAILURE;
        }

        try {
            $tenant = $provisioner->create(
                [
                    'identifier' => $identifier,
                    'name' => (string) ($this->option('name') ?? $identifier),
                    'domain' => $this->option('domain'),
                    'plan' => (string) ($this->option('plan') ?? 'free'),
                    'settings' => $settings,
                    'quota' => $quota,
                ],
                migrate: !$this->option('no-migrate'),
            );
        } catch (\Throwable $e) {
            $this->error('ساخت tenant ناموفق: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('✅ tenant «' . $tenant->identifier() . '» ساخته شد (id=' . $tenant->id() . ')');

        return self::SUCCESS;
    }

    /** @return array<string, mixed>|false */
    private function decodeJsonOption(string $name): array|false
    {
        $raw = $this->option($name);
        if (!\is_string($raw) || \trim($raw) === '') {
            return [];
        }
        $decoded = \json_decode($raw, true);

        return \is_array($decoded) ? $decoded : false;
    }
}

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
 * DatabaseTenantManager — استراتژی B: پایگاه‌دادهٔ جدا به ازای tenant (DOC-028 §3).
 *
 * اتصال پویا `tenant` از قالب `tenancy.tenant_template` (یا اتصال مرکزی)
 * + settingهای tenant (db_driver/db_host/db_database/db_username/db_password)
 * ساخته می‌شود؛ سپس اتصال پیش‌فرض به آن جابه‌جا می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class DatabaseTenantManager implements TenantStrategyInterface
{
    /** نام اتصال مرکزی — پیش از هر جابه‌جایی default ثبت می‌شود */
    private string $centralCaptured;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?ConfigRepository $config = null,
        private readonly ?DispatcherInterface $events = null,
    ) {
        $this->centralCaptured = (string) ($config?->get('tenancy.central_connection', '')
            ?: $config?->get('database.default', '') ?: '');
    }

    public function name(): string
    {
        return 'database';
    }

    public function connect(TenantInterface $tenant): void
    {
        $this->assertDb();

        $templateName = (string) ($this->config?->get('tenancy.tenant_template', '') ?: $this->centralConnection());
        $template = (array) ($this->config?->get('database.connections.' . $templateName, []) ?? []);
        if ($template === []) {
            throw TenancyException::for('tenancy.template_missing', ['template' => $templateName]);
        }

        $tenantConfig = $template;
        $tenantConfig['database'] = $this->databaseFor($tenant, $template);
        foreach (['driver', 'host', 'port', 'username', 'password', 'prefix'] as $key) {
            $setting = $tenant->setting('db_' . $key);
            if ($setting !== null && $setting !== '') {
                $tenantConfig[$key] = $setting;
            }
        }
        $encrypted = $tenant->setting('db_password_encrypted');
        if (\is_string($encrypted) && $encrypted !== '' && $this->container->bound('encrypter')) {
            $tenantConfig['password'] = $this->container->make('encrypter')->decrypt($encrypted);
        }

        $this->config?->set('database.connections.tenant', $tenantConfig);

        /** @var \Tavaan\Engine\Database\ConnectionManager $manager */
        $manager = $this->container->make('db');
        $manager->purge('tenant');
        $manager->setDefaultConnection('tenant');

        $this->events?->dispatch(new TenantConnected($tenant->id(), 'database'));
    }

    public function disconnect(): void
    {
        if (!$this->container->bound('db')) {
            return;
        }
        /** @var \Tavaan\Engine\Database\ConnectionManager $manager */
        $manager = $this->container->make('db');
        $manager->purge('tenant');
        $manager->setDefaultConnection($this->centralName());
    }

    public function provision(TenantInterface $tenant): void
    {
        $this->assertDb();
        /** @var \Tavaan\Engine\Database\ConnectionManager $manager */
        $manager = $this->container->make('db');

        $central = $manager->connection($this->centralConnection());
        $driver = $central->driver();
        $database = $this->databasePath($tenant, $driver);
        $this->assertSafeIdentifier($database, $driver === 'sqlite');

        if ($driver === 'sqlite') {
            // sqlite: database = مسیر فایل — ساخت فایل/پوشه کافی است
            $path = $database;
            $dir = \dirname($path);
            if (!\is_dir($dir) && !\mkdir($dir, 0775, true) && !\is_dir($dir)) {
                throw TenancyException::for('tenancy.provision_failed', ['reason' => 'cannot create ' . $dir]);
            }
            if (!\file_exists($path) && \touch($path) === false) {
                throw TenancyException::for('tenancy.provision_failed', ['reason' => 'cannot create ' . $path]);
            }

            return;
        }

        if ($driver === 'mysql') {
            $central->statement(
                'CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            );

            return;
        }

        if ($driver === 'pgsql') {
            $exists = $central->select('SELECT 1 FROM pg_database WHERE datname = ?', [$database]);
            if ($exists === []) {
                $central->statement('CREATE DATABASE "' . $database . '"');
            }

            return;
        }

        throw TenancyException::for('tenancy.provision_failed', ['reason' => 'unsupported driver ' . $driver]);
    }

    public function deprovision(TenantInterface $tenant): void
    {
        $this->assertDb();
        /** @var \Tavaan\Engine\Database\ConnectionManager $manager */
        $manager = $this->container->make('db');

        $central = $manager->connection($this->centralConnection());
        $driver = $central->driver();
        $database = $this->databasePath($tenant, $driver);
        $this->assertSafeIdentifier($database, $driver === 'sqlite');

        if ($driver === 'sqlite') {
            if (\is_file($database)) {
                @\unlink($database);
            }

            return;
        }
        if ($driver === 'mysql') {
            $central->statement('DROP DATABASE IF EXISTS `' . $database . '`');

            return;
        }
        if ($driver === 'pgsql') {
            $central->statement('DROP DATABASE IF EXISTS "' . $database . '"');
        }
    }

    /** @param array<string, mixed> $template */
    private function databaseFor(TenantInterface $tenant, array $template): string
    {
        $driver = (string) ($tenant->setting('db_driver') ?? $template['driver'] ?? 'sqlite');

        return $this->databasePath($tenant, $driver);
    }

    /** مسیر/نام پایگاه‌دادهٔ tenant برای یک driver مشخص. */
    private function databasePath(TenantInterface $tenant, string $driver): string
    {
        if ($driver === 'sqlite') {
            $setting = $tenant->setting('db_database');
            if (\is_string($setting) && $setting !== '') {
                return $setting;   // مسیر کامل فایل
            }
            $dir = (string) ($this->config?->get('tenancy.sqlite_path', '') ?: (\defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/storage/tenants' : 'storage/tenants'));

            return \rtrim($dir, '/') . '/' . \preg_replace('/[^A-Za-z0-9_-]/', '', $tenant->identifier()) . '.sqlite';
        }

        return $tenant->databaseName();
    }

    private function centralConnection(): string
    {
        return $this->centralName();
    }

    /**
     * نام اتصال مرکزی — صریح از config یا ثبت‌شده در زمان ساخت.
     * 🔒 پس از setDefaultConnection('tenant') دیگر نمی‌توان از
     * database.default خواند (به 'tenant' تغییر کرده است).
     */
    private function centralName(): string
    {
        $explicit = (string) ($this->config?->get('tenancy.central_connection', '') ?? '');
        if ($explicit !== '') {
            return $explicit;
        }
        if ($this->centralCaptured !== '' && $this->centralCaptured !== 'tenant') {
            return $this->centralCaptured;
        }

        return (string) ($this->config?->get('database.default', '') ?? '');
    }

    private function assertDb(): void
    {
        if (!$this->container->bound('db')) {
            throw TenancyException::for('tenancy.database_required');
        }
    }

    /**
     * 🔒 محافظ injection در CREATE/DROP DATABASE:
     * - شناسهٔ SQL فقط [A-Za-z0-9_]
     * - مسیر sqlite (بدون `..`) فقط وقتی driver=sqlite
     */
    private function assertSafeIdentifier(string $identifier, bool $isPath = false): void
    {
        if ($identifier === '') {
            throw TenancyException::for('tenancy.unsafe_identifier', ['identifier' => $identifier]);
        }
        if ($isPath) {
            if (\str_contains($identifier, '..') || \preg_match('/^[A-Za-z0-9_\/.-]+$/', $identifier) !== 1) {
                throw TenancyException::for('tenancy.unsafe_identifier', ['identifier' => $identifier]);
            }

            return;
        }
        if (\preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw TenancyException::for('tenancy.unsafe_identifier', ['identifier' => $identifier]);
        }
    }
}

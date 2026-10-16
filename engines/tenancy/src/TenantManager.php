<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Tenancy\TenantInterface;
use Tavaan\Engine\Database\QueryBuilder;
use Tavaan\Engine\Tenancy\Events\TenantCreated;
use Tavaan\Engine\Tenancy\Events\TenantResolved;
use Tavaan\Engine\Tenancy\Exceptions\TenancyException;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Tenancy
 * TenantManager — نقطهٔ ورود چنداجاره‌ای (Facade: Tenant — DOC-082E §2).
 *
 * 🔒 همهٔ خواندن/نوشتن‌های ثبت tenant از «اتصال مرکزی» انجام می‌شود
 * (نه اتصال پیش‌فرض) تا با جابه‌جایی اتصال در استراتژی B اشتباه نشود.
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantManager
{
    /** @var array<string, TenantInterface|null> memo یافتن در هر فرایند/درخواست */
    private array $memo = [];

    private bool $strategyConnected = false;

    /**
     * اتصال مرکزی ثبت‌شده در زمان ساخت.
     * 🔒 پس از جابه‌جایی default در استراتژی B، `database.default`
     * مقدار 'tenant' دارد — ثبت مرکزی هرگز نباید از آن بخواند.
     */
    private string $centralCaptured;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?ConfigRepository $config = null,
        private readonly ?DispatcherInterface $events = null,
    ) {
        $this->centralCaptured = (string) ($config?->get('tenancy.central_connection', '')
            ?: $config?->get('database.default', '') ?: '');
    }

    // ─── پیکربندی ────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function config(string $key, mixed $default = null): mixed
    {
        $value = $this->config;
        if ($value === null) {
            return $default;
        }

        return $value->get('tenancy.' . $key, $default);
    }

    public function enabled(): bool
    {
        return (bool) $this->config('enabled', false);
    }

    public function strategy(): string
    {
        $strategy = (string) $this->config('strategy', 'shared');

        return \in_array($strategy, ['shared', 'database', 'schema'], true) ? $strategy : 'shared';
    }

    /** @return class-string<Tenant> */
    public function modelClass(): string
    {
        $class = $this->config('tenant_model', Tenant::class);

        return \is_string($class) && \class_exists($class) ? $class : Tenant::class;
    }

    public function tenantColumn(): string
    {
        return (string) $this->config('tenant_column', 'tenant_id');
    }

    /** @return list<string> */
    public function ignoreTables(): array
    {
        return \array_map('strval', (array) $this->config('ignore_tables', []));
    }

    public function table(): string
    {
        return (string) $this->config('table', 'tvn_tenants');
    }

    // ─── Context ─────────────────────────────────────────────────────────

    public function current(): ?TenantInterface
    {
        $current = TenantContext::get();

        return $current instanceof TenantInterface ? $current : null;
    }

    public function id(): int|string|null
    {
        return TenantContext::id();
    }

    public function identifier(): ?string
    {
        return TenantContext::identifier();
    }

    public function setCurrent(?TenantInterface $tenant): void
    {
        TenantContext::set($tenant);
    }

    /**
     * اجرای موقت در زمینهٔ یک tenant (CLI/Job/migrateAll).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function run(TenantInterface $tenant, callable $callback): mixed
    {
        $wasConnected = $this->strategyConnected;
        $this->connectStrategy($tenant);
        try {
            return TenantContext::run($tenant, $callback);
        } finally {
            if (!$wasConnected) {
                $this->disconnectStrategy();
            }
        }
    }

    /** برقراری اتصال DB مخصوص tenant (استراتژی B/C — در middleware). */
    public function connectStrategy(TenantInterface $tenant): void
    {
        $manager = $this->strategyManager();
        if ($manager === null) {
            return;
        }
        $manager->connect($tenant);
        $this->strategyConnected = true;
    }

    /** قطع اتصال tenant و بازگشت به مرکز (پاک‌سازی — TEN-SEC-05). */
    public function disconnectStrategy(): void
    {
        if (!$this->strategyConnected) {
            return;
        }
        $this->strategyManager()?->disconnect();
        $this->strategyConnected = false;
    }

    /**
     * اجرای cross-tenant بدون Scope.
     *
     * 🔒 نیازمند مجوز `tenancy.cross_tenant` (وقتی gate موجود باشد) +
     * ثبت رویداد Audit (DOC-028 §2.5).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function withoutCurrentScope(callable $callback, ?string $reason = null): mixed
    {
        $this->authorizeCrossTenant();

        $currentId = TenantContext::id();
        $this->events?->dispatch('tenancy.cross_tenant', [
            'actor_tenant' => $currentId,
            'reason' => $reason ?? '',
            'at' => Clock::isoNow(),
        ]);

        return TenantContext::withoutCurrentScope($callback);
    }

    private function authorizeCrossTenant(): void
    {
        // در محیط CLI با دسترسی کامل (console) مجاز است؛ در HTTP باید gate بررسی شود
        if (!$this->container->bound('gate')) {
            return;
        }
        if (\PHP_SAPI === 'cli') {
            return;
        }

        try {
            $gate = $this->container->make('gate');
            if (\method_exists($gate, 'allows') && !$gate->allows('tenancy.cross_tenant')) {
                throw TenancyException::for('tenancy.cross_tenant_forbidden');
            }
        } catch (TenancyException $e) {
            throw $e;
        } catch (\Throwable) {
            // gate بدون کاربر → در HTTP یعنی غیرمجاز
            throw TenancyException::for('tenancy.cross_tenant_forbidden');
        }
    }

    // ─── CRUD ثبت tenantها ───────────────────────────────────────────────

    public function findByIdentifier(string $identifier): ?TenantInterface
    {
        return $this->memoized('id:' . $identifier, fn (): ?TenantInterface => $this->hydrate(
            $this->query()->where('identifier', $identifier)->first(),
        ));
    }

    public function findByDomain(string $domain): ?TenantInterface
    {
        return $this->memoized('dom:' . \mb_strtolower($domain), fn (): ?TenantInterface => $this->hydrate(
            $this->query()->where('domain', \mb_strtolower($domain))->first(),
        ));
    }

    public function find(int|string $id): ?TenantInterface
    {
        return $this->memoized('pk:' . $id, fn (): ?TenantInterface => $this->hydrate(
            $this->query()->where('id', $id)->first(),
        ));
    }

    /**
     * ایجاد tenant در DB مرکزی.
     *
     * @param array<string, mixed> $data identifier (اجباری)، name, domain, plan, settings(array), quota(array), is_active
     */
    public function create(array $data): TenantInterface
    {
        $identifier = \trim((string) ($data['identifier'] ?? ''));
        if ($identifier === '' || \preg_match('/^[A-Za-z0-9._-]{1,64}$/', $identifier) !== 1) {
            throw TenancyException::for('tenancy.invalid_identifier', ['identifier' => $identifier]);
        }
        if ($this->findByIdentifier($identifier) !== null) {
            throw TenancyException::for('tenancy.duplicate_identifier', ['identifier' => $identifier]);
        }

        $now = Clock::isoNow();
        $id = $this->query()->insertGetId([
            'identifier' => $identifier,
            'name' => (string) ($data['name'] ?? $identifier),
            'domain' => isset($data['domain']) ? \mb_strtolower((string) $data['domain']) : null,
            'plan' => (string) ($data['plan'] ?? 'free'),
            'settings' => $this->encodeJson($data['settings'] ?? []),
            'quota' => $this->encodeJson($data['quota'] ?? []),
            'is_active' => (int) (bool) ($data['is_active'] ?? true),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 🔒 memo قبل از insert ممکن است null کش کرده باشد — پاک می‌شود
        $this->flushMemo();

        $tenant = $this->find((int) $id)
            ?? throw TenancyException::for('tenancy.create_failed', ['identifier' => $identifier]);

        $this->events?->dispatch(new TenantCreated($tenant->id(), $tenant->identifier(), $tenant));

        return $tenant;
    }

    /**
     * به‌روزرسانی رکورد tenant.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string|TenantInterface $tenant, array $data): bool
    {
        $id = $tenant instanceof TenantInterface ? $tenant->id() : $this->resolveId($tenant);
        if ($id === null) {
            return false;
        }

        $update = [];
        foreach (['name', 'domain', 'plan'] as $field) {
            if (\array_key_exists($field, $data)) {
                $update[$field] = $field === 'domain' ? \mb_strtolower((string) $data[$field]) : $data[$field];
            }
        }
        if (\array_key_exists('settings', $data)) {
            $update['settings'] = $this->encodeJson($data['settings']);
        }
        if (\array_key_exists('quota', $data)) {
            $update['quota'] = $this->encodeJson($data['quota']);
        }
        if (\array_key_exists('is_active', $data)) {
            $update['is_active'] = (int) (bool) $data['is_active'];
        }
        if ($update === []) {
            return true;
        }
        $update['updated_at'] = Clock::isoNow();

        $ok = $this->query()->where('id', $id)->update($update) >= 0;
        $this->flushMemo();

        return $ok;
    }

    public function activate(int|string|TenantInterface $tenant): bool
    {
        return $this->update($tenant, ['is_active' => true]);
    }

    public function deactivate(int|string|TenantInterface $tenant): bool
    {
        return $this->update($tenant, ['is_active' => false]);
    }

    public function delete(int|string|TenantInterface $tenant): bool
    {
        $id = $tenant instanceof TenantInterface ? $tenant->id() : $this->resolveId($tenant);
        if ($id === null) {
            return false;
        }
        $deleted = $this->query()->where('id', $id)->delete() > 0;
        $this->flushMemo();

        return $deleted;
    }

    /** @return list<TenantInterface> */
    public function all(int $limit = 500, int $offset = 0): array
    {
        $rows = $this->query()->orderBy('id')->limit($limit)->offset($offset)->get();
        $out = [];
        foreach ($rows as $row) {
            $tenant = $this->hydrate((array) $row);
            if ($tenant !== null) {
                $out[] = $tenant;
            }
        }

        return $out;
    }

    /** @return list<TenantInterface> */
    public function active(int $limit = 500): array
    {
        $rows = $this->query()->where('is_active', 1)->orderBy('id')->limit($limit)->get();
        $out = [];
        foreach ($rows as $row) {
            $tenant = $this->hydrate((array) $row);
            if ($tenant !== null) {
                $out[] = $tenant;
            }
        }

        return $out;
    }

    public function countActive(): int
    {
        return (int) $this->query()->where('is_active', 1)->count();
    }

    /** سهمیهٔ tenant جاری (یا مشخص). */
    public function quota(string $resource, ?TenantInterface $tenant = null): ?array
    {
        return ($tenant ?? $this->current())?->quota($resource);
    }

    /** پاک‌کردن memo (پس از تغییرات یا تست). */
    public function flushMemo(): void
    {
        $this->memo = [];
    }

    /**
     * مسیر فایل‌های ایزولهٔ tenant (DOC-028 §5 — Filesystem isolation).
     *
     * 🔒 identifier sanitize می‌شود و مسیر باید داخل sandbox بماند.
     */
    public function storagePath(?string $identifier = null): string
    {
        $identifier ??= $this->identifier() ?? 'global';
        $safe = (string) \preg_replace('/[^A-Za-z0-9_-]/', '', $identifier);
        if ($safe === '') {
            $safe = 'global';
        }
        $base = \rtrim((string) $this->config('storage_path', 'storage/tenants'), '/');

        return $base . '/' . $safe;
    }

    // ─── درونی ───────────────────────────────────────────────────────────

    private function query(): QueryBuilder
    {
        if (!$this->container->bound('db')) {
            throw TenancyException::for('tenancy.database_required');
        }
        /** @var \Tavaan\Engine\Database\ConnectionManager $manager */
        $manager = $this->container->make('db');

        $central = (string) $this->config('central_connection', '');
        if ($central === '') {
            $central = $this->centralCaptured;
        }
        $connection = $central !== '' ? $manager->connection($central) : $manager->connection();

        return $connection->table($this->table());
    }

    /** @param array<string, mixed>|null $row */
    private function hydrate(?array $row): ?TenantInterface
    {
        if ($row === null || $row === []) {
            return null;
        }
        $class = $this->modelClass();
        $probe = new $class();

        return $probe->newFromRow($row);
    }

    private function resolveId(int|string|TenantInterface $tenant): int|string|null
    {
        if ($tenant instanceof TenantInterface) {
            return $tenant->id();
        }
        if (\is_int($tenant) || \ctype_digit((string) $tenant)) {
            return (int) $tenant;
        }
        $found = $this->findByIdentifier((string) $tenant);

        return $found?->id();
    }

    private function encodeJson(mixed $value): ?string
    {
        if ($value === null || $value === [] || $value === '') {
            return null;
        }
        if (\is_string($value)) {
            // از قبل JSON است؟
            \json_decode($value);

            return \json_last_error() === JSON_ERROR_NONE ? $value : null;
        }
        if (!\is_array($value)) {
            return null;
        }

        return \json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private function memoized(string $key, \Closure $factory): ?TenantInterface
    {
        if (\array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $factory();
    }

    private function strategyManager(): ?object
    {
        return match ($this->strategy()) {
            'database' => $this->container->bound(DatabaseTenantManager::class)
                ? $this->container->make(DatabaseTenantManager::class) : null,
            'schema' => $this->container->bound(SchemaTenantManager::class)
                ? $this->container->make(SchemaTenantManager::class) : null,
            default => null,
        };
    }

    /** رویداد resolve (توسط middleware فراخوانی می‌شود). */
    public function dispatchResolved(TenantInterface $tenant, string $via = 'chain'): void
    {
        $this->events?->dispatch(new TenantResolved($tenant->id(), $tenant->identifier(), $via));
    }
}

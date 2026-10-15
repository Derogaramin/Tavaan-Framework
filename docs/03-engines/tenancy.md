# DOC-082E — موتور Tenancy | چنداجاره‌ای

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-082E |
| Engine ID | `tavaan.engine.tenancy` |
| Namespace | `Tavaan\Engine\Tenancy` |
| اولویت | **P1** — فاز M6 |
| وابستگی | `database`, `cache`, `http`, `event` |
| Facade | `Tenant` |
| LOC | ۱۰۰۰ |
| مرتبط | DOC-028 (معماری Multi-Tenancy) |

> این موتور پیاده‌سازی DOC-028 است. برای تحلیل معماری و سه استراتژی، DOC-028 را ببینید.

## ۱. هدف
پیاده‌سازی سه استراتژی چنداجاره‌ای (Shared DB / DB-per-Tenant / Schema-per-Tenant)،
Tenant Context، Resolver Chain، Row-Level Scoping، Provisioning و Quota.

## ۲. API
```php
Tenant::current();                       // ?Tenant
Tenant::id();
Tenant::identifier();
Tenant::run($tenant, fn () => /* … */);  // اجرای موقت در زمینۀ tenant
Tenant::withoutCurrentScope(fn () => /* cross-tenant, با مجوز */);
Tenant::create(['identifier'=>'acme', 'plan'=>'pro']);
Tenant::findByDomain('acme.example.com');
Tenant::quota('orders_per_day');
```

## ۳. کلاس‌ها
| کلاس | مسئولیت |
|---|---|
| `Tenant` | موجود tenant (id, identifier, settings, quota) |
| `TenantContext` | context جاری (Fiber-aware) |
| `TenantManager` | ایجاد/اتصال/قطع |
| `Resolvers/*` | Domain/Header/Path/Query/Token/Default |
| `IdentifyTenantMiddleware` | resolve در HTTP |
| `TenantScope` | Row-Level Scope (استراتژی A) |
| `DatabaseTenantManager` | استراتژی B (اتصال پویا) |
| `SchemaTenantManager` | استراتژی C (search_path) |
| `QuotaGuard` | محدودیت منابع |
| `TenantConfigRepository` | تنظیمات per-tenant |

## ۴. Resolver Chain
→ DOC-028 §2.3. ترتیب: Domain → Header → Path → Token → Query → Default.

```php
final class TenantResolverChain
{
    /** @param list<TenantResolverInterface> $resolvers */
    public function __construct(private readonly array $resolvers) {}

    public function resolve(RequestInterface $request): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $id = $resolver->resolve($request);
            if ($id !== null) { return $id; }
        }
        return null;
    }
}
```

## ۵. Provisioning (استراتژی B)
```php
final class TenantProvisioner
{
    public function create(array $data): Tenant
    {
        $tenant = Tenant::create($data);                       // در DB مرکزی
        $this->createDatabase($tenant);                          // CREATE DATABASE tenant_x
        $this->migrate($tenant);                                 // اجرای migrationها در DB tenant
        $this->seed($tenant);                                    // دادهٔ اولیه
        $this->events->dispatch(new TenantCreated($tenant->id));
        return $tenant;
    }

    public function migrateAll(): void
    {
        foreach (Tenant::active()->cursor() as $tenant) {
            Tenant::run($tenant, fn () => Artisan::call('migrate', ['--force'=>true]));
            \usleep(50_000);    // rate limit
        }
    }
}
```

## ۶. Row-Level Scope (استراتژی A)
→ DOC-028 §2.4. `TenantScope` روی همهٔ Modelهای tenant-aware.

```php
// Observer برای پرکردن خودکار tenant_id در insert
final class TenantObserver
{
    public function creating(Model $model): void
    {
        if ($model->isTenantAware() && $model->getAttribute('tenant_id') === null) {
            $tenantId = TenantContext::id();
            if ($tenantId === null) {
                throw new TenantNotResolvedException(messageId: 'tenancy.context_missing');
            }
            $model->setAttribute('tenant_id', $tenantId);
        }
    }
}
```
🔒 `tenant_id` در `guarded` (کاربر نمی‌تواند تغییر دهد).
🔒 `tvn db:tenant-guard` کوئری‌های بدون scope را گزارش می‌دهد.

## ۷. Cache/Queue/Filesystem Isolation
→ DOC-028 §5. پیاده‌سازی:
- Cache: prefix `tvn:t{tenantId}:`
- Queue: `tenant_id` در payload + `TenantContext::run` در worker
- Filesystem: `storage/tenants/{id}/`
- Session: tenant check در middleware

## ۸. Quota
→ DOC-028 §6.

## ۹. امنیت
→ DOC-028 §8 (۱۰ کنترل TEN-SEC).

## ۱۰. پیکربندی
```php
// config/tenancy.php
return [
    'enabled' => env_bool('TVN_TENANCY', false),
    'strategy' => env_string('TVN_TENANCY_STRATEGY', 'shared'),  // shared|database|schema
    'central_connection' => 'mysql',
    'tenant_model' => \App\Models\Tenant::class,
    'resolver' => ['domain','header','path','token'],
    'tenant_column' => 'tenant_id',
    'ignore_tables' => ['tvn_tenants','tvn_migrations','users'],   // جداول سراسری
    'quota' => ['enabled'=>true, 'defaults'=>[]],
];
```

## ۱۱. تست
→ DOC-028 §9.
| T-01 | resolve از domain/header/path/token |
| T-02 | Row-Level isolation |
| T-03 | Cache/Queue/FS isolation |
| T-04 | Provisioning (استراتژی B) |
| T-05 | Quota |
| T-06 | Resident context cleanup |
| T-07 | Cross-tenant leakage fuzzing |

## ۱۲. معیار پذیرش 🧪
- AC-TEN-01..08 (مطابق DOC-028 §10).

## ۱۳. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.tenancy.v1
engine:
  id: tavaan.engine.tenancy
  namespace: Tavaan\Engine\Tenancy
  path: engines/tenancy
  priority: P1
  phase: M6
  depends_on: [database, cache, http, event]
  facade: {name: Tenant, accessor: tenant}
  tables: [tvn_tenants]
  strategies: [shared, database, schema]
  config_file: config/tenancy.php
  public_api:
    - class: TenantManager
      methods: [current, id, identifier, run, withoutCurrentScope, create, findByDomain]
  loc_estimate: 1000
```

# DOC-028 — معماری چنداجاره‌ای (Multi-Tenancy)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-028 |
| وضعیت | Approved v1.0 |
| موتور مسئول | `engines/tenancy` (زیرمجموعهٔ `database` + `cache` + `filesystem`) |
| کاربرد | SaaS، سامانه‌های سازمانی چندشرکتی، چندفروشگاهی |

---

## ۱. سه استراتژی

| استراتی | جداسازی | هزینه | مقیاس | پیچیدگی | انتخاب |
|---|---|---|---|---|---|
| **A. Shared DB + `tenant_id`** | منطقی (Row-Level) | کم | ۱۰۰۰+ tenant | کم | ✅ پیش‌فرض |
| **B. DB per Tenant** | فیزیکی | زیاد | ≤ ۵۰۰ | متوسط | سازمانی/امنیتی |
| **C. Schema per Tenant** | نیمه‌فیزیکی | متوسط | ≤ ۲۰۰۰ | متوسط | PostgreSQL |
| D. Hybrid | ترکیبی | — | — | بالا | با ADR |

---

## ۲. استراتژی A — Shared Database

### ۲٫۱ Schema
```sql
-- همهٔ جداول tenant-aware ستون tenant_id دارند
ALTER TABLE shop_orders ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL AFTER id;
CREATE INDEX idx_orders_tenant ON shop_orders (tenant_id, created_at);

-- جدول ثبت tenant
CREATE TABLE tvn_tenants (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  identifier   VARCHAR(64)  NOT NULL,          -- slug یا subdomain
  name         VARCHAR(190) NOT NULL,
  domain       VARCHAR(190) NULL,
  plan         VARCHAR(50)  NOT NULL DEFAULT 'free',
  settings     JSON         NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  quota        JSON         NULL,              -- محدودیت منابع
  created_at   DATETIME(3)  NOT NULL,
  updated_at   DATETIME(3)  NULL,
  UNIQUE KEY uk_tenant_identifier (identifier),
  KEY idx_tenant_domain (domain)
) ENGINE=InnoDB;
```

### ۲٫۲ Tenant Context
```php
final class TenantContext
{
    private static ?Tenant $current = null;

    public static function set(?Tenant $tenant): void { self::$current = $tenant; }
    public static function get(): ?Tenant { return self::$current; }
    public static function id(): ?int { return self::$current?->id; }
    public static function identifier(): ?string { return self::$current?->identifier; }

    /**
     * اجرای موقت در زمینۀ یک tenant دیگر (برای CLI/Job).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = self::$current;
        self::$current = $tenant;
        try {
            return $callback();
        } finally {
            self::$current = $previous;
        }
    }

    public static function clear(): void { self::$current = null; }
}
```
🔒 در حالت Resident (Swoole)، Context باید **Fiber-aware** باشد:
```php
private static function store(): ContextStore
{
    return \class_exists(\Swoole\Coroutine::class) && \Swoole\Coroutine::getCid() > 0
        ? ContextStore::coroutine()
        : ContextStore::fiber();
}
```

### ۲٫۳ Tenant Resolver (Middleware)
```php
final class IdentifyTenantMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly TenantResolverChain $resolvers,
    ) {}

    public function handle(RequestInterface $request, \Closure $next, string ...$p): ResponseInterface
    {
        $identifier = $this->resolvers->resolve($request);   // domain → header → path → session

        if ($identifier === null) {
            throw new TenantNotFoundException(messageId: 'tenancy.not_found');
        }

        $tenant = $this->tenants->findByIdentifier($identifier);

        if (!$tenant->isActive()) {
            throw new TenantSuspendedException(messageId: 'tenancy.suspended');
        }
        if ($tenant->quotaExceeded()) {
            throw new TenantQuotaExceededException(messageId: 'tenancy.quota');
        }

        TenantContext::set($tenant);
        return $next($request->withAttribute('tenant', $tenant));
    }

    public function terminate(RequestInterface $r, ResponseInterface $res): void
    {
        TenantContext::clear();
    }
}
```

**زرنجیر Resolver (به ترتیب):**
1. `DomainResolver` — `shop1.example.com`
2. `HeaderResolver` — `X-Tenant-Id`
3. `PathResolver` — `/t/shop1/...`
4. `QueryResolver` — `?tenant=shop1` (فقط dev)
5. `TokenResolver` — از `tenant_id` در JWT
6. `DefaultResolver` — tenant پیش‌فرض

### ۲٫۴ Row-Level Scoping خودکار
```php
// Global Scope در ORM
final class TenantScope implements ScopeInterface
{
    public function apply(QueryBuilder $query, Model $model): void
    {
        $tenantId = TenantContext::id();
        if ($tenantId === null && ! $model->allowGlobalTenant()) {
            throw new TenantNotResolvedException(messageId: 'tenancy.context_missing');
        }
        if ($tenantId !== null) {
            $query->where($model->getTable() . '.' . $model->getTenantColumn(), $tenantId);
        }
    }
}
```
🔒 **هرگز** یک کوئری بدون Scope روی جدول tenant-aware اجرا نشود.
🔒 Insert باید `tenant_id` را خودکار پر کند (Observer).
🔒 یک `tvn db:tenant-guard` برای بررسی Query Log و یافتن کوئری‌های بدون Scope.

### ۲٫۵ بدون Scope (Cross-Tenant) — فقط CLI/Admin
```php
Tenant::withoutCurrentScope(function () {
    return $this->db->table('shop_orders')->where('status', 'paid')->count();
});
```
🔒 نیازمند مجوز `tenancy.cross_tenant` + ثبت Audit.

---

## ۳. استراتژی B — Database per Tenant

```
config/database.php
'connections' => [
    'central' => [...],                       // DB اصلی: tenants, users سراسری
    'tenant'  => [
        'driver'   => 'mysql',
        'database' => '',                      // در زمان اجرا پر می‌شود
        'dynamic'  => true,
    ],
],
```

### ۳٫۱ Tenant Manager
```php
final class DatabaseTenantManager implements TenantManager
{
    public function connect(Tenant $tenant): void
    {
        $connection = $this->factory->make([
            'driver'   => $tenant->settings['db_driver']   ?? 'mysql',
            'host'     => $tenant->settings['db_host']     ?? config('db.connections.tenant.host'),
            'database' => $tenant->databaseName(),
            'username' => $tenant->settings['db_username'] ?? config('db.connections.tenant.username'),
            'password' => $this->secrets->decrypt($tenant->encryptedDbPassword()),
        ], name: 'tenant');

        $this->connections->set('tenant', $connection);
        $this->cache->flushScoped();         // پاک‌کردن کش مرتبط
        $this->events->dispatch(new TenantConnected($tenant->id));
    }

    public function disconnect(): void { /* … */ }
}
```

### ۳٫۲ Provisioning
| دستور | عملیات |
|---|---|
| `tvn tenant:create <id>` | ساخت رکورد + DB + migrate + seed |
| `tvn tenant:migrate` | اجرای migration روی همهٔ tenantها (با Rate Limit) |
| `tvn tenant:migrate --tenant=x` | فقط یک tenant |
| `tvn tenant:delete <id>` | Archive → Drop (با تأیید دوگانه) |
| `tvn tenant:list` | فهرست + وضعیت + حجم |
| `tvn tenant:stats` | مصرف منابع |

---

## ۴. استراتژی C — Schema per Tenant (PostgreSQL)

```sql
CREATE SCHEMA tenant_shop1;
SET search_path TO tenant_shop1, public;
```
- Migration روی همهٔ schemaها با `tvn tenant:migrate --strategy=schema`
- Connection Pool با `search_path` پویا

---

## ۵. جداسازی سایر منابع

| منبع | استراتژی A | استراتژی B |
|---|---|---|
| **Cache** | Prefix: `tvn:t{tenantId}:...` | Prefix جدا یا Instance جدا |
| **Filesystem** | `storage/tenants/{id}/...` | Disk جدا |
| **Queue** | Job با `tenant_id` در Payload + Context Restore | Queue Name جدا |
| **Session** | `session.tenant_check` (تغییر tenant → invalidate) | جدا |
| **Log** | Field `tenant_id` در همهٔ رکوردها | Channel جدا |
| **Search Index** | `index_prefix = t{id}_` | Index جدا |
| **Mail** | From/Reply-To per tenant + Template | جدا |
| **Domain/SSL** | Wildcard یا per-tenant Certificate | — |

### ۵٫۱ Queue در حالت چنداجاره‌ای
```php
final class TenantAwareJob implements JobInterface
{
    public function handle(): void
    {
        TenantContext::run($this->tenant, function (): void {
            ($this->inner)();
        });
    }
}
```
🔒 Worker باید پس از هر Job، `TenantContext::clear()` کند.

---

## ۶. Quota و محدودیت منابع

```json
{
  "quotas": {
    "users":           {"limit": 100,    "period": null},
    "products":        {"limit": 10000,  "period": null},
    "orders_per_day":  {"limit": 1000,   "period": "day"},
    "storage_mb":      {"limit": 5120,   "period": null},
    "api_calls_per_min":{"limit": 300,   "period": "minute"},
    "emails_per_day":  {"limit": 5000,   "period": "day"}
  }
}
```

```php
final class QuotaGuard
{
    /** @throws TenantQuotaExceededException */
    public function assertAvailable(string $resource, int $amount = 1, ?Tenant $tenant = null): void
    {
        $tenant ??= TenantContext::get() ?? throw new TenantNotResolvedException();
        $quota = $tenant->quota($resource);
        if ($quota === null) { return; }

        $used = $this->counter->get($tenant->id, $resource, $quota->period);
        if ($used + $amount > $quota->limit) {
            $this->events->dispatch(new TenantQuotaExceeded($tenant->id, $resource));
            throw new TenantQuotaExceededException(
                messageId: 'tenancy.quota_exceeded',
                context: ['resource' => $resource, 'limit' => $quota->limit, 'used' => $used],
            );
        }
    }

    public function consume(string $resource, int $amount = 1): void { /* increment در Cache/DB */ }
}
```

---

## ۷. تنظیمات tenant (Per-Tenant Config)

```
اولویت (بالاتر → پایین‌تر):
1. Tenant Settings (DB/JSON)
2. Plan Defaults
3. App Config (config/*.php)
4. Framework Defaults
```

```php
final class TenantConfigRepository implements RepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        $tenant = TenantContext::get();
        if ($tenant !== null) {
            $value = $tenant->setting($key, sentinel: '__MISSING__');
            if ($value !== '__MISSING__') { return $value; }
        }
        return $this->base->get($key, $default);
    }
}
```

---

## ۸. امنیت چنداجاره‌ای 🔒

| ID | کنترل |
|---|---|
| TEN-SEC-01 | 🔒 هر کوئری روی جدول tenant-aware باید Scope داشته باشد (بررسی خودکار) |
| TEN-SEC-02 | 🔒 تغییر `tenant_id` از ورودی کاربر ممنوع (Mass Assignment Guard) |
| TEN-SEC-03 | 🔒 Cache Key حتماً شامل tenant باشد |
| TEN-SEC-04 | 🔒 مسیر فایل حتماً داخل sandbox tenant باشد |
| TEN-SEC-05 | 🔒 در Resident Mode، Context پس از هر درخواست پاک شود |
| TEN-SEC-06 | 🔒 Queue Job بدون tenant context → Fail (نه اجرا با tenant قبلی) |
| TEN-SEC-07 | ⚠️ تست Cross-Tenant Leakage خودکار (Fuzzing با دو tenant) |
| TEN-SEC-08 | 🔒 Admin سطح «Super» با Audit و 2FA |
| TEN-SEC-09 | 🔒 Backup/Export فقط tenant جاری (مگر مجوز سراسری) |
| TEN-SEC-10 | 🔒 Rate Limit per-tenant نه فقط per-IP |

---

## ۹. تست چنداجاره‌ای

```php
final class TenantIsolationTest extends TestCase
{
    public function testOrders_AreIsolatedBetweenTenants(): void
    {
        $tenantA = $this->createTenant('a');
        $tenantB = $this->createTenant('b');

        $this->actingAsTenant($tenantA)->create(Order::class, ['total' => 100]);
        $this->actingAsTenant($tenantB)->create(Order::class, ['total' => 200]);

        $this->actingAsTenant($tenantA);
        self::assertCount(1, Order::all());
        self::assertSame(100, Order::first()->total->amount());

        $this->actingAsTenant($tenantB);
        self::assertCount(1, Order::all());
    }

    public function testCacheKeys_AreTenantScoped(): void
    {
        $this->actingAsTenant($this->createTenant('a'));
        Cache::put('key', 'A');

        $this->actingAsTenant($this->createTenant('b'));
        self::assertNull(Cache::get('key'));
    }
}
```

---

## ۱۰. معیار پذیرش 🧪

- AC-TEN-01: در استراتژی A، هیچ کوئری بدون `tenant_id` روی جداول tenant-aware (بررسی `query.executed`).
- AC-TEN-02: Resolve tenant از Subdomain/Header/Path/Token درست کار می‌کند.
- AC-TEN-03: نشت داده بین دو tenant = ۰ (تست Fuzzing با ۱۰۰ حالت).
- AC-TEN-04: Cache/Session/Queue/Filesystem کاملاً جدا هستند.
- AC-TEN-05: در Resident Mode پس از ۱۰۰۰ درخواست، Context نشت نمی‌کند.
- AC-TEN-06: Quota به‌درستی محدود و `TenantQuotaExceededException` پرتاب می‌کند.
- AC-TEN-07: `tvn tenant:migrate` روی ۱۰۰ tenant (استراتژی B) با Rate Limit و Resume.
- AC-TEN-08: تغییر استراتژی (A→B) با ابزار `tvn tenant:migrate-strategy` ممکن است.

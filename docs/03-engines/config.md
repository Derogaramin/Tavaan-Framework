# DOC-032 — موتور Config | پیکربندی و محیط

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-032 |
| Engine ID | `tavaan.engine.config` |
| Namespace | `Tavaan\Engine\Config` |
| اولویت | **P0** — فاز M1 |
| وابستگی | `container` |
| Facade | `Config` → `config` / `Env` → `env` |
| LOC | ۶۰۰ |

---

## ۱. هدف

یک مخزن پیکربندی **یکپارچه، کش‌پذیر، نوع‌دار و دارای Schema** که جایگزین
`illuminate/config` + `vlucas/phpdotenv` شود — بدون هیچ وابستگی خارجی.

- ✅ بارگذاری `.env` (بدون `putenv`)، چند‌فایلی، override با اولویت
- ✅ Dot-Notation (`app.cache.driver`)، `set/get/has/forget/pull/prepend/push`
- ✅ کش کل Config در یک فایل (تولید با `tvn config:cache`)
- ✅ Merge تنظیمات ماژول/موتور با اولویت مشخص
- ✅ Schema Validation (نوع، بازه، مقادیر مجاز) در DEV
- ✅ Immutable/Frozen در PROD
- ✅ Hot Reload امن (برای Config Server)

---

## ۲. مفاهیم

| واژه | تعریف |
|---|---|
| **Repository** | مخزن اصلی با آرایهٔ تودرتو |
| **Namespace** | بخش اول کلید (`app.*`, `db.*`) = نام فایل |
| **Env** | متغیرهای محیطی از `.env`/`$_SERVER` |
| **Schema** | توصیف نوع و قید هر کلید |
| **Frozen** | حالت فقط‌خواندنی در PROD |
| **Override Chain** | `default < module < app < env < runtime` |

---

## ۳. معماری

```
      ┌──────────┐   ┌──────────────┐   ┌───────────────┐
      │ .env     │──▶│ EnvLoader    │──▶│ EnvRepository │◀── $_SERVER/$_ENV
      └──────────┘   └──────────────┘   └───────┬───────┘
                                                │ env()
      ┌──────────┐   ┌──────────────┐           ▼
      │config/*.php│─▶│ ConfigLoader │──▶ ┌──────────────┐   ┌───────────┐
      └──────────┘   └──────────────┘    │  Repository  │──▶│  Schema   │
      ┌──────────┐          │            │  (dot-path)  │   │ Validator │
      │module cfg│──────────┘            └──────┬───────┘   └───────────┘
      └──────────┘                              │
      ┌──────────┐                              ▼
      │cache cfg │────────────────────▶ ┌──────────────┐
      └──────────┘                      │ Facade Config│
                                        └──────────────┘
```

### ۳٫۱ کلاس‌ها

| کلاس | مسئولیت |
|---|---|
| `Repository` | دسترسی dot-notation، get/set/has |
| `EnvLoader` | پارس `.env` |
| `EnvRepository` | نگهداری ENV با cast نوع |
| `ConfigLoader` | بارگذاری فایل‌ها + merge |
| `ConfigCacher` | تولید/بارگذاری کش |
| `SchemaValidator` | اعتبارسنجی نوع و بازه |
| `ConfigServiceProvider` | ثبت |
| `Exceptions/*` | `ConfigException`, `ConfigFrozenException`, `EnvParseException` |

---

## ۴. API

### ۴٫۱ Repository

| متد | امضا | توضیح |
|---|---|---|
| `get` | `get(string $key, mixed $default = null): mixed` | خواندن |
| `set` | `set(string\|array $key, mixed $value = null): void` | نوشتن (در Frozen → خطا) |
| `has` | `has(string $key): bool` | وجود |
| `missing` | `missing(string $key): bool` | نبود |
| `forget` | `forget(string $key): void` | حذف |
| `pull` | `pull(string $key, mixed $default = null): mixed` | خواندن + حذف |
| `push` | `push(string $key, mixed $value): void` | افزودن به آرایه |
| `prepend` | `prepend(string $key, mixed $value): void` | افزودن به ابتدا |
| `all` | `all(): array` | کل درخت |
| `only` | `only(array $keys): array` | زیرمجموعه |
| `except` | `except(array $keys): array` | به‌جز |
| `namespace` | `namespace(string $ns): array` | کل یک namespace |
| `freeze` | `freeze(): void` | فقط‌خواندنی |
| `isFrozen` | `isFrozen(): bool` | — |
| `toJson` | `toJson(bool $maskSecrets = true): string` | برای `tvn about` |
| `requiredKeys` | `requiredKeys(array $keys): void` | بررسی وجود اجباری در بوت |

### ۴٫۲ نمونه

```php
Config::get('app.name');                    // 'Tavaan'
Config::get('db.connections.mysql.host', '127.0.0.1');
Config::has('cache.stores.redis');           // true
Config::set('app.maintenance', true);        // فقط در dev
Config::push('http.middleware.global', MyMiddleware::class);
Config::namespace('mail');                   // ['default' => ..., 'mailers' => [...]]
```

### ۴٫۳ Env API
```php
Env::get('TVN_DB_HOST');                 // string|null
Env::string('TVN_APP_NAME', 'Tavaan');
Env::bool('TVN_APP_DEBUG', false);
Env::int('TVN_QUEUE_MAX_TRIES', 3);
Env::float('TVN_RATE', 1.0);
Env::list('TVN_TRUSTED_PROXIES', ',');   // ['10.0.0.1','10.0.0.2']
Env::json('TVN_EXTRA');                  // array|null
Env::required(['TVN_APP_KEY']);          // خطا اگر نبود
Env::all(masked: true);                  // برای دیباگ
```

---

## ۵. الگوریتم‌ها

### ۵٫۱ Dot-Path Get (بدون `explode` دوباره)
```php
public function get(string $key, mixed $default = null): mixed
{
    if (\array_key_exists($key, $this->items)) {
        return $this->items[$key];
    }
    $segments = \explode('.', $key);
    $value = $this->items;
    foreach ($segments as $segment) {
        if (!\is_array($value) || !\array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}
```
⚠️ بهینه‌سازی: کش نتیجه برای کلیدهای پرتکرار (LRU با حداکثر ۵۰۰ ورودی).

### ۵٫۲ Dot-Path Set
```php
public function set(string|array $key, mixed $value = null): void
{
    $this->assertNotFrozen();
    if (\is_array($key)) {
        foreach ($key as $k => $v) { $this->set($k, $v); }
        return;
    }
    $segments = \explode('.', $key);
    $target =& $this->items;
    while (\count($segments) > 1) {
        $segment = \array_shift($segments);
        if (!isset($target[$segment]) || !\is_array($target[$segment])) {
            $target[$segment] = [];
        }
        $target =& $target[$segment];
    }
    $target[\array_shift($segments)] = $value;
    $this->cache->clear();   // invalidate LRU
}
```

### ۵٫۳ Merge با اولویت
```
priority (کم → زیاد):
  1. defaults درون موتور (engines/*/config/*.php)
  2. module config (modules/*/config/*.php)
  3. app config (config/*.php)
  4. env-based config (config/env/*.php)     ← فقط در صورت وجود
  5. runtime set()                            ← فقط dev
```
```php
private function mergeDeep(array $base, array $override): array
{
    foreach ($override as $k => $v) {
        if (\is_array($v) && isset($base[$k]) && \is_array($base[$k])) {
            // اگر list است → جایگزینی کامل (نه ادغام شاخص‌ها)
            $base[$k] = \array_is_list($v) ? $v : $this->mergeDeep($base[$k], $v);
        } else {
            $base[$k] = $v;
        }
    }
    return $base;
}
```
🔒 قاعده: **Listها جایگزین می‌شوند، Mapها ادغام** — جلوگیری از رفتار غافلگیرکننده.

### ۵٫۴ Config Cache
```php
public function cache(string $file): void
{
    $all = $this->all();
    $this->assertSerializable($all);        // بدون Closure
    $php = "<?php\n\n// Generated by `tvn config:cache` — DO NOT EDIT.\n// Generated at: "
         . \date('c') . "\n\nreturn " . \var_export($all, true) . ";\n";
    $this->write($file, $php);              // atomic: temp + rename
}
```
🔒 اگر Closure در Config باشد → `ConfigNotCacheableException` با مسیر کلید.

### ۵٫۵ Schema Validation
```php
// config/schema/app.schema.php
return [
    'name'            => ['type' => 'string', 'max' => 100, 'required' => true],
    'env'             => ['type' => 'string', 'in' => ['local','development','testing','staging','production']],
    'debug'           => ['type' => 'bool'],
    'timezone'        => ['type' => 'string', 'rule' => 'timezone'],
    'locale'          => ['type' => 'string', 'pattern' => '/^[a-z]{2}(_[A-Z]{2})?$/'],
    'key'             => ['type' => 'string', 'pattern' => '/^base64:[A-Za-z0-9+\/=]{44}$/', 'secret' => true],
    'maintenance.driver' => ['type' => 'string', 'in' => ['file','cache','redis']],
];
```
قواعد نوع: `string|int|float|bool|array|list|map|null|mixed|class-string|callable`.
قیدها: `required, nullable, min, max, in, pattern, rule, secret, deprecated, since`.

🔒 در `env=production` فقط `required` و `in` بررسی می‌شود (سرعت)؛ در dev همه.

---

## ۶. پیکربندی خودِ موتور

```php
// engines/config/config/config.php
return [
    'paths' => [
        base_path('config'),
    ],
    'env_file' => env_string('TVN_ENV_FILE', base_path('.env')),
    'env_fallback' => [base_path('.env.local')],
    'cache' => [
        'enabled' => env_bool('TVN_CONFIG_CACHE', false),
        'file'    => storage_path('cache/config.php'),
    ],
    'schema' => [
        'enabled' => env_bool('TVN_CONFIG_SCHEMA', true),
        'path'    => base_path('config/schema'),
        'strict'  => env_string('TVN_APP_ENV', 'production') !== 'production',
    ],
    'freeze_in_production' => true,
    'secret_keys' => ['*.password', '*.secret', '*.key', '*.token', 'db.*.password', 'mail.*.password'],
];
```

---

## ۷. قراردادها
- `Tavaan\Core\Contracts\Config\RepositoryInterface`
- `Tavaan\Core\Contracts\Config\EnvRepositoryInterface`
- `Tavaan\Core\Contracts\Config\ConfigCacheInterface`

---

## ۸. نقاط توسعه
| نوع | نام |
|---|---|
| Event | `config.loaded`, `config.changed`, `config.cache.generated` |
| Contract | `ConfigLoaderInterface` (برای Config Server: etcd/Consul) |
| Extend | `Repository` با Decorator (مثل `TenantConfigRepository`) |
| Rule | قواعد سفارشی Schema (`SchemaValidator::extend('jalali_date', …)`) |

---

## ۹. امنیت

| تهدید | کنترل |
|---|---|
| نشت Secret در لاگ/`about` | `secret_keys` + Masking خودکار (`****`) |
| تغییر Config در PROD | `freeze()` → `ConfigFrozenException` |
| `.env` در وب | 🔒 بررسی در `tvn doctor` + `.htaccess` deny |
| Cache Poisoning | atomic write + permission 0640 + hash در هدر فایل |
| Closure در cache | `assertSerializable` |
| ENV Injection | `isValidKey` فقط `[A-Z_][A-Z0-9_]*` |

```php
public function masked(): array
{
    return Arr::maskRecursive($this->items, $this->secretPatterns, replacement: '********');
}
```

---

## ۱۰. کارایی

| عملیات | هدف |
|---|---|
| `get()` با LRU hit | ≤ ۰٫۳µs |
| `get()` miss (۴ سطح) | ≤ ۱٫۵µs |
| بارگذاری ۱۵ فایل config (بدون کش) | ≤ ۴ms |
| بارگذاری config کش‌شده | ≤ ۰٫۶ms |
| `config:cache` تولید | ≤ ۵۰ms |
| حافظهٔ Config کامل | ≤ ۰٫۸MB |

---

## ۱۱. خطاها

| استثنا | کد | MessageId |
|---|---|---|
| `ConfigException` | TCF001 | `config.generic` |
| `ConfigFrozenException` | TCF002 | `config.frozen` |
| `ConfigNotCacheableException` | TCF003 | `config.not_cacheable` |
| `EnvParseException` | TCF004 | `core.env.parse_failed` |
| `SchemaValidationException` | TCF005 | `config.schema_invalid` |
| `MissingConfigKeyException` | TCF006 | `config.missing_key` |

---

## ۱۲. تست

| ID | سناریو |
|---|---|
| T-01 | Dot-get در ۱/۲/۵ سطح عمق |
| T-02 | `set` روی مسیر ناموجود → ساخت خودکار |
| T-03 | Merge list = جایگزینی؛ map = ادغام |
| T-04 | `.env` با quote، escape، inline comment، `true/false/null/empty` |
| T-05 | کلید نامعتبر در `.env` → `EnvParseException` با شمارهٔ خط |
| T-06 | Config Cache roundtrip یکسان |
| T-07 | Closure در config → `ConfigNotCacheableException` |
| T-08 | Frozen + `set()` → خطا |
| T-09 | Schema: نوع اشتباه/`in` نامعتبر → خطا با مسیر کلید |
| T-10 | Masking: `db.password` در `toJson()` → `********` |
| T-11 | `Env::list` با جداکنندهٔ سفارشی |
| T-12 | اولویت: module < app < env |

---

## ۱۳. معیار پذیرش 🧪

- AC-CFG-01: همهٔ تست‌های §12 سبز.
- AC-CFG-02: `tvn config:cache && tvn config:clear` بدون خطا.
- AC-CFG-03: در PROD، `Config::set()` خطا می‌دهد.
- AC-CFG-04: `tvn about --json` هیچ Secret خامی ندارد.
- AC-CFG-05: بارگذاری config کش‌شده ≤ ۰٫۶ms.
- AC-CFG-06: بدون `putenv` (بررسی AST).

---

## ۱۴. مراجع

| منبع | الهام | تفاوت |
|---|---|---|
| `vlucas/phpdotenv` | پارس `.env` | بدون `putenv`، بدون immutability پیچیده، cast بهتر |
| Laravel `config` | Dot-Notation | افزودن Schema، Freeze، LRU |
| Symfony Config | Tree Builder | ساده‌سازی به PHP array Schema |
| 12-Factor App | Config in ENV | — |

---

## ۱۵. بلوک ماشین‌خوان 🧠

```yaml
# machine-readable: tavaan.engine.config.v1
engine:
  id: tavaan.engine.config
  namespace: Tavaan\Engine\Config
  path: engines/config
  priority: P0
  phase: M1
  depends_on: [container]
  extensions: {required: [], optional: []}
  facade: [{name: Config, accessor: config}, {name: Env, accessor: env}]
  tables: []
  events_emitted: [config.loaded, config.changed, config.cache.generated]
  config_file: engines/config/config/config.php
  public_api:
    - class: Repository
      methods: [get, set, has, missing, forget, pull, push, prepend, all, only,
                except, namespace, freeze, isFrozen, toJson, masked, requiredKeys]
    - class: EnvRepository
      methods: [get, string, bool, int, float, list, json, required, all, has]
  loc_estimate: 600
  acceptance:
    - {id: AC-CFG-01, test: 'engines/config/tests/Unit/RepositoryTest.php'}
    - {id: AC-CFG-03, test: 'engines/config/tests/Feature/FrozenConfigTest.php'}
```

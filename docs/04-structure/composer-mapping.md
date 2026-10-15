# DOC-082 — نگاشت Autoloader ↔ composer.json ↔ PSR-4 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-082 |
| هدف | توضیح دقیق اینکه «بدون composer» چگونه نگاشت namespace انجام می‌شود و چگونه در صورت نیاز با composer هم سازگار است |
| مرتبط | DOC-027 (Autoloader)، DOC-012 (Naming) |

---

## ۱. دو حالت نگاشت

| حالت | توضیح | کاربرد |
|---|---|---|
| **حالت خودکفا (Self-Sufficient)** | `Tavaan\Core\Autoloading\Autoloader` — بدون composer | 🔒 پیش‌فرض و اجباری در زمان اجرا |
| **حالت سازگار (Composer Parity)** | `composer.json` تولید می‌شود تا بتوان موتور را به‌صورت پکیج منتشر کرد | 💡 انتشار در Packagist/Privat |

> اصل P1 (صفر‌وابستگی) به این معناست که **زمان اجرا** به composer نیاز نیست؛ نه اینکه composer ممنوع باشد.
> `composer.json` صرفاً «متادیتای انتشار» است.

---

## ۲. جدول نگاشت کامل (منبع حقیقت)

| Namespace Prefix | مسیر | نوع | توضیح |
|---|---|---|---|
| `Tavaan\Core\` | `core/` | psr-4 | هسته (به‌جز Support) |
| `Tavaan\Support\` | `core/Support/` | psr-4 | Helper/VO/Collection/Attributes |
| `Tavaan\Engine\<Pascal>\` | `engines/<kebab>/src/` | psr-4 + wildcard | موتورها |
| `Tavaan\Testing\` | `engines/test/src/` | psr-4 | ابزار تست |
| `Tavaan\Tests\` | `tests/` | psr-4 | تست‌های هسته |
| `App\` | `app/src/` | psr-4 | اپلیکیشن اصلی |
| `Modules\<Pascal>\` | `modules/<kebab>/src/` | psr-4 + wildcard | ماژول‌ها |
| `Kits\<Pascal>\` | `kits/<kebab>/src/` | psr-4 + wildcard | کیت‌ها |
| `Plugins\<Pascal>\` | `plugins/<kebab>/src/` | psr-4 + wildcard | پلاگین‌ها |
| `Database\Seeders\` | `database/seeders/` | psr-4 | Seeder |
| `Database\Factories\` | `database/factories/` | psr-4 | Factory |
| `Tests\` | `tests/` | psr-4 | تست اپلیکیشن |

### ۲٫۱ قواعد تبدیل نام
```
Pascal → kebab:
  HttpClient   → http-client
  FeatureFlag  → feature-flag
  I18n         → i18n
  ORM          → orm
  Rbac         → rbac

kebab → Pascal:
  http-client  → HttpClient
  i18n         → I18n
  orm          → Orm  (استثنا: نگاشت دستی در config)
```
🔒 استثناها در `config/autoload.php` → `alias_map` ثبت می‌شوند:
```php
'alias_map' => [
    'engines/orm'         => 'ORM',
    'engines/i18n'        => 'I18n',
    'engines/rbac'        => 'Rbac',
    'engines/http-client' => 'HttpClient',
],
```

---

## ۳. فایل `config/autoload.php` (منبع پیکربندی)

```php
<?php
declare(strict_types=1);

/**
 * پیکربندی Autoloader خودنوشتهٔ تَوان.
 *
 * @see docs/04-structure/composer-mapping.md
 */

return [
    'psr-4' => [
        // ── هسته ───────────────────────────────────────────────
        'Tavaan\\Core\\'     => [__DIR__ . '/../core/'],
        'Tavaan\\Support\\'  => [__DIR__ . '/../core/Support/'],

        // ── موتورها (wildcard) ─────────────────────────────────
        'Tavaan\\Engine\\'   => [__DIR__ . '/../engines/*/src/'],
        'Tavaan\\Testing\\'  => [__DIR__ . '/../engines/test/src/'],

        // ── اپلیکیشن ───────────────────────────────────────────
        'App\\'              => [__DIR__ . '/../app/src/'],
        'Modules\\'          => [__DIR__ . '/../modules/*/src/'],
        'Kits\\'             => [__DIR__ . '/../kits/*/src/'],
        'Plugins\\'          => [__DIR__ . '/../plugins/*/src/'],

        // ── داده ───────────────────────────────────────────────
        'Database\\Seeders\\'   => [__DIR__ . '/../database/seeders/'],
        'Database\\Factories\\' => [__DIR__ . '/../database/factories/'],

        // ── تست ────────────────────────────────────────────────
        'Tavaan\\Tests\\'    => [__DIR__ . '/../tests/'],
        'Tests\\'            => [__DIR__ . '/../tests/'],
    ],

    // نگاشت دستی برای نام‌های خاص
    'alias_map' => [
        'engines/orm'         => 'ORM',
        'engines/i18n'        => 'I18n',
        'engines/rbac'        => 'Rbac',
        'engines/http-client' => 'HttpClient',
        'engines/feature-flag'=> 'FeatureFlag',
    ],

    // فایل‌هایی که باید همیشه require شوند
    'files' => [
        __DIR__ . '/../core/Support/helpers.php',
    ],

    // ClassMap ثابت (کلاس‌های پرکاربرد)
    'classmap' => [
        __DIR__ . '/../core/Application/Application.php',
        __DIR__ . '/../core/Autoloading/Autoloader.php',
        __DIR__ . '/../core/Error/ErrorHandler.php',
    ],

    // مسیرهای حذف‌شده از اسکن
    'exclude' => [
        '/tests/', '/Tests/', '/docs/', '/examples/', '/benchmarks/',
        '/vendor/', '/node_modules/',
    ],

    // prefixهای قفل‌شده (غیرقابل override توسط ماژول/پلاگین)
    'locked_prefixes' => [
        'Tavaan\\Core\\',
        'Tavaan\\Support\\',
        'Tavaan\\Engine\\',
    ],

    // کش ClassMap
    'cache' => [
        'enabled' => env_bool('TVN_AUTOLOAD_CACHE', false),
        'file'    => __DIR__ . '/../storage/cache/classmap.php',
    ],

    // حداکثر عمق اسکن
    'scan_depth' => 12,
];
```

---

## ۴. حالت سازگاری با Composer

### ۴٫۱ `composer.json` ریشه (فقط dev + autoload)

```json
{
  "name": "tavaan/tavaan",
  "description": "Tavaan — Zero-dependency PHP 8.2+ framework (HMVC / Modular / Event-Driven)",
  "type": "project",
  "license": "MIT",
  "require": {
    "php": ">=8.2.0"
  },
  "require-dev": {
    "phpstan/phpstan": "^2.0",
    "friendsofphp/php-cs-fixer": "^3.0",
    "infection/infection": "^0.29",
    "phpunit/phpunit": "^11.0"
  },
  "suggest": {
    "ext-redis": "درایور Redis برای cache/session/queue",
    "ext-pdo_mysql": "درایور MySQL",
    "ext-pdo_pgsql": "درایور PostgreSQL",
    "ext-intl": "بهبود i18n (fallback داخلی موجود است)",
    "ext-gd": "پردازش تصویر (fallback: بدون پردازش)",
    "ext-imagick": "پردازش تصویر پیشرفته",
    "ext-swoole": "حالت Resident/Coroutine",
    "ext-opcache": "کش Opcode",
    "ext-openssl": "رمزنگاری",
    "ext-mbstring": "رشتهٔ چندبایتی"
  },
  "autoload": {
    "psr-4": {
      "Tavaan\\Core\\":    "core/",
      "Tavaan\\Support\\": "core/Support/",
      "Tavaan\\Engine\\":  "engines/",
      "App\\":             "app/src/",
      "Modules\\":         "modules/",
      "Database\\Seeders\\":"database/seeders/",
      "Database\\Factories\\":"database/factories/"
    },
    "files": ["core/Support/helpers.php"]
  },
  "autoload-dev": {
    "psr-4": {
      "Tavaan\\Tests\\": "tests/",
      "Tests\\":         "tests/"
    }
  },
  "scripts": {
    "test":  "php bin/tvn test",
    "bench": "php bin/tvn bench:all",
    "lint":  "php bin/tvn lint:php",
    "stan":  "phpstan analyse --level=max",
    "cs":    "php-cs-fixer fix --dry-run --diff",
    "qa":    ["@lint", "@cs", "@stan", "@test"]
  },
  "config": {
    "optimize-autoloader": true,
    "sort-packages": true,
    "platform-check": true
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

> ⚠️ توجه: در `autoload.psr-4` کامپوزر، نگاشت `Tavaan\Engine\` → `engines/` با ساختار
> `engines/<kebab>/src/` **سازگار نیست** (چون composer kebab→Pascal را نمی‌فهمد).
> راه‌حل‌ها:
> 1. 🔒 **راه‌حل اصلی:** از Autoloader خودنوشته استفاده شود (composer autoload غیرفعال).
> 2. 💡 در انتشار هر موتور به‌صورت پکیج مستقل، `composer.json` همان موتور با
>    `"Tavaan\\Engine\\Cache\\": "src/"` منتشر می‌شود (جدول §۵).
> 3. ⚠️ در صورت اصرار بر composer در ریشه، از `classmap` تولیدشده با `tvn autoload:cache` استفاده شود.

### ۴٫۲ غیرفعال‌سازی autoload کامپوزر در زمان اجرا
```php
// public/index.php — هرگز این خط وجود ندارد:
// require __DIR__ . '/../vendor/autoload.php';   ❌ ممنوع

require TVN_BASE_PATH . '/core/bootstrap.php';   ✅ تنها مسیر
```

🔒 تست CI: اگر پوشهٔ `vendor/` وجود داشت و پروژه بدون آن هم کار کرد → پاس.
```bash
mv vendor vendor.bak 2>/dev/null || true
php bin/tvn about && php bin/tvn test
mv vendor.bak vendor 2>/dev/null || true
```

---

## ۵. `composer.json` هر موتور (برای انتشار)

```json
{
  "name": "tavaan/engine-cache",
  "description": "Tavaan Cache Engine — zero-dependency caching with tagging, locks and 6 drivers",
  "type": "library",
  "license": "MIT",
  "keywords": ["tavaan", "cache", "redis", "apcu"],
  "require": {
    "php": ">=8.2.0"
  },
  "suggest": {
    "ext-redis": "Redis driver",
    "ext-apcu": "APCu driver",
    "ext-memcached": "Memcached driver"
  },
  "autoload": {
    "psr-4": { "Tavaan\\Engine\\Cache\\": "src/" }
  },
  "autoload-dev": {
    "psr-4": { "Tavaan\\Tests\\Engine\\Cache\\": "tests/" }
  },
  "extra": {
    "tavaan": {
      "engine": "cache",
      "provider": "Tavaan\\Engine\\Cache\\CacheServiceProvider",
      "facade": "Tavaan\\Core\\Facade\\Facades\\Cache",
      "config": "config/cache.php",
      "docs": "docs/03-engines/cache.md"
    }
  },
  "scripts": {
    "test": "php ../../../bin/tvn test --engine=cache"
  },
  "minimum-stability": "stable"
}
```

🔒 **قاعدهٔ طلایی:** `"require"` هرگز نباید شامل یک پکیج باشد. فقط `php` و `ext-*` در `suggest`.

---

## ۶. تولید خودکار `composer.json` از `module.json`

```bash
tvn engine:publish <name> --with-composer
tvn module:publish <id>   --with-composer
```
خروجی: نگاشت از `module.json` → `composer.json` مطابق جدول زیر.

| فیلد `module.json` | فیلد `composer.json` |
|---|---|
| `id` = `tavaan.engine.cache` | `name` = `tavaan/engine-cache` |
| `title_en` | `description` |
| `license` | `license` |
| `requires.php` | `require.php` |
| `optional_extensions[].name` | `suggest.ext-*` |
| `autoload.psr-4` | `autoload.psr-4` |
| `providers[0]` | `extra.tavaan.provider` |
| `docs` | `extra.tavaan.docs` |

---

## ۷. نگاشت در IDE (برای Developer Experience)

### ۷٫۱ PhpStorm / IntelliJ
- Mark `core/`, `engines/*/src/`, `app/src/`, `modules/*/src/` به‌عنوان **Sources Root**.
- Mark `storage/`, `tests/` به‌عنوان **Excluded**.
- فایل `tools/ide/phpstorm.meta.php` برای تکمیل خودکار `Container::make()`:

```php
<?php
namespace PHPSTORM_META;

override(\Tavaan\Core\Contracts\Container\ContainerInterface::make(0),
    map([
        'config'   => \Tavaan\Engine\Config\Repository::class,
        'events'   => \Tavaan\Engine\Event\EventDispatcher::class,
        'cache'    => \Tavaan\Core\Contracts\Cache\RepositoryInterface::class,
        'db'       => \Tavaan\Core\Contracts\Database\ConnectionInterface::class,
        'log'      => \Tavaan\Core\Contracts\Log\LoggerInterface::class,
        ''         => '@',      // fallback: بازگرداندن همان رشته به‌عنوان class
    ])
);

override(\Tavaan\Core\Facade\Facade::getFacadeRoot(0), map(['' => '@']));
expectedArguments(\Tavaan\Engine\Config\Repository::get(), 0,
    'app.name', 'app.env', 'app.debug', 'cache.default', 'db.default');
```

### ۷٫۲ VS Code (Intelephense)
`settings.json`:
```json
{
  "intelephense.environment.phpVersion": "8.2.0",
  "intelephense.files.associations": {"*.tv.php": "php"},
  "intelephense.files.exclude": ["**/storage/**", "**/vendor/**/{Tests,tests}/**"],
  "intelephense.stubs": ["apache","bcmath","bz2","calendar","ctype","curl","date","dom",
    "gd","hash","intl","json","mbstring","mysqli","openssl","pcntl","pdo","pdo_mysql",
    "pdo_pgsql","pdo_sqlite","phar","posix","random","redis","session","sockets",
    "sqlite3","standard","tokenizer","xml","zip","zlib","swoole"]
}
```

### ۷٫۳ تولید Stub
```bash
tvn ide:generate --format=phpstorm   # → tools/ide/phpstorm.meta.php
tvn ide:generate --format=intelephense
tvn ide:generate --format=stubs       # → tools/ide/stubs/*.php (برای Facade)
```

---

## ۸. کشف خودکار کلاس‌ها (Class Discovery)

برای مواردی که نیاز به اسکن کلاس‌ها داریم (Commands، Events، Policies، Rules):

```php
final class ClassFinder
{
    /**
     * یافتن همهٔ کلاس‌های یک namespace که Interface/Attribute خاصی دارند.
     *
     * @param class-string|list<class-string> $implements
     * @return list<class-string>
     */
    public function find(string $namespacePrefix, string|array|null $implements = null,
                         ?string $hasAttribute = null, bool $useCache = true): array
    {
        $cacheKey = 'classfinder:' . hash('xxh128', $namespacePrefix . json_encode($implements) . $hasAttribute);

        if ($useCache && ($cached = $this->cache->get($cacheKey)) !== null) {
            return $cached;
        }

        $files  = $this->scanFiles($namespacePrefix);
        $result = [];

        foreach ($files as $file) {
            $class = $this->classFromFile($file, $namespacePrefix);
            if ($class === null || !\class_exists($class)) {
                continue;
            }
            if ($implements !== null && !$this->implementsAny($class, (array) $implements)) { continue; }
            if ($hasAttribute !== null && !$this->hasAttribute($class, $hasAttribute)) { continue; }
            $result[] = $class;
        }

        \sort($result, SORT_STRING);     // 🔒 قطعی
        if ($useCache) { $this->cache->forever($cacheKey, $result); }
        return $result;
    }
}
```
🔒 Discovery باید **کش** شود (اسکن فایل‌ها گران است).
🔒 خروجی **مرتب** باشد (قطعیت).
🔒 در PROD از `storage/cache/classmap.php` استفاده شود، نه اسکن.

---

## ۹. قواعد Helper Functions (فایل `helpers.php`)

| قاعده |
|---|
| 🔒 حداکثر ۴۰ تابع سراسری در کل فریمورک |
| 🔒 همه با پیشوند معنایی کوتاه و بدون تداخل: `config()`, `event()`, `cache()`, `view()`, `route()`, `trans()`, `e()`, `now()`, `collect()`, `app()`, `request()`, `response()`, `redirect()`, `auth()`, `session()`, `validator()`, `storage()`, `url()`, `asset()`, `dd()`, `dump()`, `retry()`, `tap()`, `value()`, `with()`, `abort()`, `rescue()`, `module()`, `tenant()`, `money()`, `jalali()` |
| 🔒 هر تابع فقط یک Delegate به Facade/Container (بدون منطق) |
| 🔒 هر تابع دارای PHPDoc کامل با `@return` دقیق |
| 🔒 بررسی تداخل نام با `function_exists()` |
| ❌ ممنوع: `helper` با منطق پیچیده، State، I/O |

```php
// core/Support/helpers.php (excerpt)
declare(strict_types=1);

use Tavaan\Core\Facade\Facades\App;
use Tavaan\Core\Facade\Facades\Config;

if (!\function_exists('app')) {
    /**
     * دسترسی به Container یا Resolve یک سرویس.
     *
     * @template T
     * @param class-string<T>|null $abstract
     * @return ($abstract is null ? \Tavaan\Core\Contracts\Container\ContainerInterface : T)
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $container = App::getFacadeRoot();
        return $abstract === null ? $container : $container->make($abstract, $parameters);
    }
}

if (!\function_exists('config')) {
    /** @return ($key is null ? \Tavaan\Core\Contracts\Config\RepositoryInterface : mixed) */
    function config(?string $key = null, mixed $default = null): mixed
    {
        $repo = Config::getFacadeRoot();
        return $key === null ? $repo : $repo->get($key, $default);
    }
}

if (!\function_exists('e')) {
    /** Escape برای HTML — همیشه استفاده شود */
    function e(mixed $value, bool $doubleEncode = true): string
    {
        return \htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', $doubleEncode);
    }
}
```

---

## ۱۰. معیار پذیرش 🧪

- AC-LOAD-01: پروژه بدون `vendor/` بالا می‌آید و همهٔ کلاس‌ها Resolve می‌شوند.
- AC-LOAD-02: `tvn autoload:cache` یک ClassMap تولید می‌کند که ۱۰۰٪ کلاس‌ها را پوشش دهد.
- AC-LOAD-03: با ClassMap کش‌شده، زمان Resolve اولین کلاس ≥ ۷۰٪ کاهش یابد.
- AC-LOAD-04: `tvn engine:publish cache --with-composer` یک `composer.json` معتبر تولید کند که با `composer validate` پاس شود.
- AC-LOAD-05: override یک prefix قفل‌شده → `AutoloadException`.
- AC-LOAD-06: ClassFinder با کش، خروجی یکسان و مرتب در دو اجرای متوالی می‌دهد.
- AC-LOAD-07: همهٔ توابع `helpers.php` با `function_exists` محافظت شده‌اند (تست بارگذاری دوباره).

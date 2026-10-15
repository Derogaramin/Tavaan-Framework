# DOC-027 — راه‌اندازی و بارگذاری خودکار (Bootstrapping & Autoloading) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-027 |
| موتور | `engines/autoloader`, `core/Bootstrap` |
| والد | DOC-025 (فازهای P1..P6) |
| قید | **بدون `vendor/autoload.php`** — همه‌چیز خودنوشته |

---

## ۱. زنجیرهٔ راه‌اندازی

```
public/index.php
   │
   ├─▶ require core/bootstrap.php          (تنها require دستی مجاز)
   │      │
   │      ├─ 1. تعریف ثابت‌ها (TVN_BASE_PATH, TVN_START, TVN_VERSION)
   │      ├─ 2. بررسی حداقل نسخهٔ PHP (≥ 8.2.0)
   │      ├─ 3. require core/Autoloader.php  (تنها require دوم)
   │      ├─ 4. Autoloader::register()
   │      ├─ 5. Autoloader::addNamespaceMap(از config/autoload.php)
   │      ├─ 6. ErrorManager::bootstrapEarly()   ← برای خطاهای حین بوت
   │      └─ 7. بازگرداندن کنترل
   │
   └─▶ ApplicationBuilder::create()->boot()
          │
          ├─ B1  LoadBootstrappers (فهرست زیر)
          ├─ B2  Environment
          ├─ B3  Config
          ├─ B4  ErrorHandling (کامل)
          ├─ B5  Container (Core Bindings)
          ├─ B6  Facade
          ├─ B7  Modules Discovery
          ├─ B8  Providers Register
          ├─ B9  Providers Boot
          └─ B10 Kernel Ready
```

### ۱٫۱ Bootstrapperهای استاندارد

| # | کلاس | مسئولیت | وابستگی |
|---|---|---|---|
| 1 | `LoadEnvironmentVariables` | خواندن `.env` | Autoloader |
| 2 | `LoadConfiguration` | بارگذاری `config/*.php` + Cache | Env |
| 3 | `HandleExceptions` | ErrorHandler/ExceptionHandler/Shutdown | Config |
| 4 | `RegisterFacades` | اتصال Facade به Container | Container |
| 5 | `RegisterProviders` | ثبت Providerها (با Deferrable) | Config |
| 6 | `DiscoverModules` | خواندن manifest و Topological Sort | Providers |
| 7 | `BootProviders` | اجرای `boot()` | Discover |
| 8 | `LoadCaches` | Route/View/Event Cache (در PROD) | Config |
| 9 | `SetLocaleAndTimezone` | `date_default_timezone_set`, locale | Config |
| 10 | `RegisterShutdownTasks` | `register_shutdown_function` | — |

🔒 Bootstrapperها باید **idempotent** باشند (در حالت Resident دوباره اجرا نشوند).

---

## ۲. Autoloader خودنوشته

### ۲٫۱ الزامات
| ID | الزام |
|---|---|
| AL-01 | سازگار با منطق PSR-4 (بدون نیاز به Composer) |
| AL-02 | پشتیبانی از `psr-4`, `psr-0`, `classmap`, `files` |
| AL-03 | کش ClassMap برای PROD (یک فایل PHP با `return []`) |
| AL-04 | O(1) برای ClassMap، O(log n)/O(1) برای Prefix Map |
| AL-05 | عدم Load دو‌بارهٔ یک کلاس |
| AL-06 | گزارش خطای واضح برای کلاس یافت‌نشده (با پیشنهاد) |
| AL-07 | پشتیبانی از Prefix Length Sorting (طولانی‌تر اول) |
| AL-08 | Thread/Fiber-Safe (بدون State جهانی mutable جز کش) |
| AL-09 | Dev Mode: کشف خودکار از `autoload.php` config (بدون Cache) |
| AL-10 | قابلیت `dumpClassMap()` برای تولید کش |

### ۲٫۲ ساختار

```php
<?php
declare(strict_types=1);

namespace Tavaan\Core\Autoloading;

/**
 * بارگذار خودکار کلاس‌ها — بدون Composer.
 *
 * @api 1.0
 */
final class Autoloader
{
    /** @var array<string, list<string>> prefix → list of directories (PSR-4) */
    private array $prefixes = [];

    /** @var array<string, string> FQCN → file path */
    private array $classMap = [];

    /** @var list<string> فایل‌هایی که باید در ابتدا require شوند */
    private array $files = [];

    /** @var array<string, true> */
    private array $loaded = [];

    private bool $registered = false;

    private function __construct(private readonly string $basePath) {}

    public static function create(string $basePath): self
    {
        return new self(\rtrim($basePath, '/\\'));
    }

    public function register(bool $prepend = true): void
    {
        if ($this->registered) {
            return;
        }
        \spl_autoload_register($this->loadClass(...), prepend: $prepend);
        $this->registered = true;
        $this->loadFiles();
    }

    public function unregister(): void { /* spl_autoload_unregister */ }

    /** افزودن نگاشت PSR-4: 'Tavaan\' → ['core/', 'engines/'] */
    public function addNamespace(string $prefix, string|array $paths, bool $prepend = false): void
    {
        $prefix = $this->normalizePrefix($prefix);
        $paths  = \array_map($this->normalizePath(...), (array) $paths);

        if ($prepend) {
            $this->prefixes[$prefix] = \array_merge($paths, $this->prefixes[$prefix] ?? []);
        } else {
            $this->prefixes[$prefix] = \array_merge($this->prefixes[$prefix] ?? [], $paths);
        }
        $this->sortPrefixesByLength();
    }

    /** @param array<string, string> $map */
    public function addClassMap(array $map): void
    {
        foreach ($map as $class => $file) {
            $this->classMap[$class] = $file;
        }
    }

    public function addFile(string $path): void
    {
        $this->files[] = $this->normalizePath($path);
    }

    /**
     * تلاش برای بارگذاری کلاس.
     *
     * @return true اگر بارگذاری شد
     */
    public function loadClass(string $class): bool
    {
        if (isset($this->loaded[$class])) {
            return true;
        }

        // 1) ClassMap — سریع‌ترین مسیر
        if (isset($this->classMap[$class])) {
            return $this->includeFile($this->classMap[$class], $class);
        }

        // 2) PSR-4 Prefix
        foreach ($this->prefixes as $prefix => $dirs) {
            if (!\str_starts_with($class, $prefix)) {
                continue;
            }
            $relative = \substr($class, \strlen($prefix));
            $relativePath = \str_replace('\\', '/', $relative) . '.php';

            foreach ($dirs as $dir) {
                $file = $dir . '/' . $relativePath;
                if (\is_file($file)) {
                    return $this->includeFile($file, $class);
                }
            }
        }

        return false;   // اجازه به autoloader بعدی
    }

    private function includeFile(string $file, string $class): bool
    {
        if (!\is_file($file)) {
            return false;
        }
        require $file;

        if (!\class_exists($class, false)
            && !\interface_exists($class, false)
            && !\trait_exists($class, false)
            && !\enum_exists($class, false)) {
            throw new AutoloadException(
                messageId: 'core.autoload.class_not_declared',
                context: ['class' => $class, 'file' => $file],
                hint: 'نام کلاس درون فایل با نام فایل/namespace مطابقت ندارد.',
            );
        }
        $this->loaded[$class] = true;
        return true;
    }

    /** تولید ClassMap برای کش (tvn optimize) */
    public function dumpClassMap(?string $outputFile = null): array
    {
        $map = [];
        foreach ($this->prefixes as $prefix => $dirs) {
            foreach ($dirs as $dir) {
                foreach ($this->scanDirectory($dir) as $file) {
                    $class = $this->fileToClass($file, $dir, $prefix);
                    if ($class !== null) {
                        $map[$class] = $file;
                    }
                }
            }
        }
        \ksort($map);
        if ($outputFile !== null) {
            $this->writePhpArray($outputFile, $map);
        }
        return $map;
    }

    /** @return list<string> */
    public function loadedClasses(): array { return \array_keys($this->loaded); }
    public function stats(): AutoloaderStats { /* … */ }
}
```

### ۲٫۳ نرمال‌سازی‌ها 🔒

```php
private function normalizePrefix(string $prefix): string
{
    $prefix = \trim($prefix, '\\');
    return $prefix === '' ? '' : $prefix . '\\';
}

private function normalizePath(string $path): string
{
    $path = \str_replace('\\', '/', $path);
    if ($path[0] !== '/' && !\preg_match('#^[A-Za-z]:/#', $path)) {
        $path = $this->basePath . '/' . $path;
    }
    return \rtrim($this->resolveSymlinks($path), '/');
}

/** مرتب‌سازی بر اساس طول prefix (نزولی) تا دقیق‌ترین اولویت یابد */
private function sortPrefixesByLength(): void
{
    \uksort($this->prefixes, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));
}
```

### ۲٫۴ امنیت Autoloader 🔒
| خطر | کنترل |
|---|---|
| Path Traversal از نام کلاس | رد کردن نام شامل `..`, `/`, `\0` و هر کاراکتر غیر `[A-Za-z0-9_\\]` |
| بارگذاری فایل خارج از basePath | `realpath` باید داخل basePath باشد |
| بارگذاری دو‌باره | `$this->loaded` |
| DoS با نام‌های طولانی | محدودیت طول نام کلاس ≤ ۲۵۵ |
| کلاس جعلی با نام مشابه هسته | Prefix Map هسته **اول** ثبت و غیرقابل override (Lock) |

```php
private function assertSafeClassName(string $class): void
{
    if (\strlen($class) > 255 || \preg_match('#[^A-Za-z0-9_\\\\]#', $class) === 1) {
        throw new AutoloadException(messageId: 'core.autoload.unsafe_class_name', context: ['class' => $class]);
    }
}

private function assertInsideBasePath(string $realPath): void
{
    if (!\str_starts_with($realPath, $this->basePath)) {
        throw new AutoloadException(messageId: 'core.autoload.path_escape', context: ['file' => $realPath]);
    }
}
```

---

## ۳. نگاشت Namespace (پیش‌فرض)

```php
// config/autoload.php
return [
    'psr-4' => [
        'Tavaan\\Core\\'    => ['core/'],
        'Tavaan\\Support\\' => ['core/Support/'],
        'Tavaan\\Engine\\'  => ['engines/*/src/'],     // الگوی wildcard
        'Tavaan\\Testing\\' => ['engines/test/src/'],
        'App\\'             => ['app/src/'],
        'Modules\\'         => ['modules/*/src/'],      // الگوی wildcard
        'Kits\\'            => ['kits/*/src/'],
        'Plugins\\'         => ['plugins/*/src/'],
        'Database\\'        => ['database/'],
        'Tests\\'           => ['tests/'],
    ],
    'classmap' => [
        // مسیرهای خاص
        'core/helpers.php',
    ],
    'files' => [
        'core/Support/helpers.php',      // توابع سراسری (کم و کنترل‌شده)
    ],
    'exclude' => [
        '*/tests/*', '*/Tests/*', '*/docs/*', '*/examples/*',
    ],
    'cache' => [
        'enabled' => env('TVN_AUTOLOAD_CACHE', false),
        'file'    => 'storage/cache/classmap.php',
    ],
    'locked_prefixes' => ['Tavaan\\Core\\', 'Tavaan\\Engine\\'],
];
```

### ۳٫۱ الگوی Wildcard (`engines/*/src/`)
```
resolveWildcard('Tavaan\\Engine\\', 'engines/*/src/'):
   1. glob(basePath/engines/*/src) → list of dirs
   2. برای هر dir، نام پوشه را kebab → Pascal تبدیل کن
   3. ثبت prefix دقیق‌تر: 'Tavaan\Engine\Cache\' → 'engines/cache/src/'
   4. کش نتیجه (در PROD از classmap استفاده شود)
```
🔒 در DEV، wildcard هر درخواست glob می‌شود (گران) → توصیه: `tvn optimize` در توسعه.

---

## ۴. Environment Loader (`.env`)

```php
final class EnvLoader
{
    /**
     * پارس `.env` بدون putenv (امنیت و سازگاری با Swoole).
     *
     * @return array<string, string|bool|int|float|null>
     */
    public function load(string $file, bool $override = false): array
    {
        if (!\is_file($file) || !\is_readable($file)) {
            return [];
        }

        $vars = [];
        $lines = \file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $lineNumber => $line) {
            $line = \trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = \strpos($line, '=');
            if ($pos === false) {
                continue;   // یا throw با شمارهٔ خط در dev
            }
            $key   = \trim(\substr($line, 0, $pos));
            $value = $this->parseValue(\trim(\substr($line, $pos + 1)));

            if (!$this->isValidKey($key)) {
                throw new EnvParseException(
                    messageId: 'core.env.invalid_key',
                    context: ['line' => $lineNumber + 1, 'key' => $key],
                );
            }
            if ($override || !isset($vars[$key])) {
                $vars[$key] = $value;
            }
        }
        return $vars;
    }

    private function parseValue(string $raw): string|bool|int|float|null
    {
        // quote handling
        if (\strlen($raw) >= 2) {
            $first = $raw[0]; $last = $raw[-1];
            if ($first === '"' && $last === '"') {
                return \stripcslashes(\substr($raw, 1, -1));   // escape sequences
            }
            if ($first === "'" && $last === "'") {
                return \substr($raw, 1, -1);                    // literal
            }
        }
        // inline comment
        $hash = \strpos($raw, ' #');
        if ($hash !== false) { $raw = \rtrim(\substr($raw, 0, $hash)); }

        return match (\strtolower($raw)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => \preg_match('/^-?\d+$/', $raw) === 1
                                   ? (int) $raw
                                   : (\is_numeric($raw) ? (float) $raw : $raw),
        };
    }

    private function isValidKey(string $key): bool
    {
        return \preg_match('/^[A-Z_][A-Z0-9_]*$/', $key) === 1;
    }
}
```

### ۴٫۱ قواعد ENV 🔒
| # | قاعده |
|---|---|
| E1 | 🔒 اولویت: `$_SERVER`/`$_ENV` → `.env` → پیش‌فرض config |
| E2 | 🔒 **هرگز** `putenv()` (در Thread/Swoole ناپایدار و ناامن) |
| E3 | 🔒 `.env` نباید در `public/` یا Git باشد؛ `.env.example` منتشر شود |
| E4 | 🔒 مقادیر حساس با `#[\SensitiveParameter]` و بدون Log |
| E5 | ⚠️ `.env.testing` و `.env.production` با اولویت |
| E6 | 🔒 اگر `app.env === 'production'` و `app.debug === true` → خطای بوت |
| E7 | ⚠️ `tvn env:check` برای یافتن کلیدهای گمشده نسبت به `.env.example` |

---

## ۵. Config Loading

```
config/
├── app.php          → app.*
├── autoload.php     → autoload.*
├── cache.php        → cache.*
├── database.php     → db.*
├── event.php        → event.*
├── http.php         → http.*
├── log.php          → log.*
├── mail.php         → mail.*
├── modules.php      → modules.*
├── providers.php    → providers (list)
├── queue.php        → queue.*
├── security.php     → security.*
├── session.php      → session.*
├── view.php         → view.*
└── <module>.php     → ادغام خودکار از ماژول‌ها
```

### ۵٫۱ الگوریتم بارگذاری
```
1. اگر storage/cache/config.php وجود دارد و env=production → load یک فایل (سریع)
2. وگرنه:
   a. فهرست فایل‌های config/*.php
   b. برای هر فایل: key = basename بدون پسوند → load lazy
   c. mergeConfigFrom برای ماژول‌ها (با اولویت: module < app < env)
3. Resolve `env()` calls → جایگزینی با مقادیر EnvRepository
4. Schema Validation (اختیاری) با `config/schema/*.php`
5. Frozen در PROD (تغییر → ConfigFrozenException)
```

### ۵٫۲ `env()` Helper
```php
function env(string $key, mixed $default = null): mixed
{
    return EnvRepository::instance()->get($key, $default);
}
function env_bool(string $key, bool $default = false): bool { /* … */ }
function env_int(string $key, int $default = 0): int { /* … */ }
function env_string(string $key, string $default = ''): string { /* … */ }
function env_list(string $key, string $sep = ','): array { /* … */ }
```
🔒 `env()` فقط در فایل‌های `config/` مجاز است — در کد کاربردی `Config::get()` استفاده شود
(چون Config Cache می‌شود، `env()` در زمان Cache اجرا نمی‌شود).

---

## ۶. کش‌سازی (Optimization)

| دستور | خروجی | اثر |
|---|---|---|
| `tvn optimize` | همهٔ موارد زیر | — |
| `tvn config:cache` | `storage/cache/config.php` | حذف پارس ۱۵ فایل |
| `tvn route:cache` | `storage/cache/routes.php` | حذف ساخت RouteCollection |
| `tvn autoload:cache` | `storage/cache/classmap.php` | حذف glob/scan |
| `tvn view:cache` | `storage/cache/views/*.php` | کامپایل همهٔ Viewها |
| `tvn event:cache` | `storage/cache/events.php` | حذف Discovery |
| `tvn container:compile` | `storage/cache/container.php` | حذف Reflection |
| `tvn preload:generate` | `storage/cache/preload.php` | Opcache Preload |
| `tvn optimize:clear` | حذف همه | — |

### ۶٫۱ Opcache Preload
```php
// storage/cache/preload.php  (تولید خودکار)
<?php
opcache_compile_file('/var/www/tavaan/core/Container/Container.php');
opcache_compile_file('/var/www/tavaan/core/Events/EventDispatcher.php');
// … کلاس‌های پرمصرف (بر اساس Profile)
```
در `php.ini`: `opcache.preload=/var/www/tavaan/storage/cache/preload.php`
> ⚠️ Preload با `opcache.preload_user` (نه root).
> 🔒 تغییر هر فایل Preloaded نیازمند Restart PHP-FPM.

---

## ۷. ApplicationBuilder (Fluent API)

```php
final class ApplicationBuilder
{
    private array $bootstrappers = [];
    private array $providerFiles = [];
    private ?string $envFile = null;

    public static function create(string $basePath): self { /* … */ }

    public function withEnvFile(string $file): self { $this->envFile = $file; return $this; }
    public function withConfig(string $path): self { /* … */ }
    public function withModules(string ...$paths): self { /* … */ }
    public function withProviders(array $providers): self { /* … */ }
    public function withProvidersFromFile(string $file): self { /* … */ }
    public function withBootstrapper(string $class): self { /* … */ }
    public function withoutBootstrapper(string $class): self { /* … */ }
    public function forConsole(): self { /* حذف HTTP-specific bootstrapperها */ }
    public function forQueue(): self { /* … */ }
    public function forTesting(): self { /* Array drivers, no cache */ }

    public function boot(): Application
    {
        $app = new Application($this->basePath);
        foreach ($this->resolveBootstrappers() as $bootstrapper) {
            $app->make($bootstrapper)->bootstrap($app);
        }
        return $app;
    }
}
```

---

## ۸. حالت‌های اجرا (Runtime Modes)

| حالت | تشخیص | تفاوت |
|---|---|---|
| `http` | `PHP_SAPI !== 'cli'` | همهٔ Bootstrapper |
| `console` | `PHP_SAPI === 'cli'` و `bin/tvn` | بدون Session/CSRF، با Console Kernel |
| `queue` | `queue:work` | Resident + Job Context |
| `scheduler` | `schedule:run` | One-shot |
| `realtime` | `serve:realtime` | Fiber/Swoole |
| `testing` | `TVN_ENV=testing` | Array drivers، بدون Cache |
| `repl` | `tvn tinker` | Container آماده + خواندن خط |

```php
interface ApplicationInterface
{
    public function runningInConsole(): bool;
    public function runningUnitTests(): bool;
    public function environment(): string;
    public function isProduction(): bool;
    public function isLocal(): bool;
    public function mode(): RuntimeMode;
}

enum RuntimeMode: string
{
    case Http = 'http';
    case Console = 'console';
    case Queue = 'queue';
    case Scheduler = 'scheduler';
    case Realtime = 'realtime';
    case Testing = 'testing';
    case Repl = 'repl';
}
```

---

## ۹. Shutdown و پاکسازی

```php
final class ShutdownManager
{
    /** @var list<callable> */
    private array $tasks = [];

    public function register(callable $task, int $priority = 0): void { /* … */ }

    public function handle(): void
    {
        // 1) خطای فاجعه‌بار؟
        $error = \error_get_last();
        if ($this->isFatal($error)) {
            $this->handler->handleFatal($error);
        }
        // 2) اجرای taskها (اولویت‌دار، با Timeout)
        foreach ($this->sorted() as $task) {
            try { $task(); } catch (\Throwable $e) { $this->logger->error('shutdown.task_failed', [...]); }
        }
        // 3) Provider::shutdown()
        // 4) Log Flush
    }
}
```

🔒 در Resident (Swoole): Shutdown در پایان هر Request **نه** پایان فرایند.

---

## ۱۰. `core/bootstrap.php` — مرجع کامل

```php
<?php

/**
 * تَوان | Tavaan — Bootstrap
 * تنها فایل require دستی در Entry Point.
 */

declare(strict_types=1);

use Tavaan\Core\Autoloading\Autoloader;
use Tavaan\Core\Error\EarlyErrorManager;

// ── ۱) ثابت‌ها ───────────────────────────────────────────────────────────
\define('TVN_VERSION', '1.0.0');
\defined('TVN_START')     || \define('TVN_START', \microtime(true));
\defined('TVN_BASE_PATH') || \define('TVN_BASE_PATH', \dirname(__DIR__));

// ── ۲) بررسی نسخهٔ PHP ───────────────────────────────────────────────────
if (PHP_VERSION_ID < 80200) {
    \fwrite(STDERR, "Tavaan requires PHP >= 8.2.0. Current: " . PHP_VERSION . PHP_EOL);
    exit(1);
}

// ── ۳) تنظیمات اولیهٔ خطا ────────────────────────────────────────────────
\error_reporting(E_ALL);
\ini_set('display_errors', '0');
\ini_set('log_errors', '1');

// ── ۴) ثبت Autoloader ────────────────────────────────────────────────────
require TVN_BASE_PATH . '/core/Autoloading/Autoloader.php';

$autoloader = Autoloader::create(TVN_BASE_PATH);

// نگاشت هسته (Hardcoded تا قبل از خواندن config)
$autoloader->addNamespace('Tavaan\\Core\\',    'core/');
$autoloader->addNamespace('Tavaan\\Support\\', 'core/Support/');
$autoloader->addNamespace('Tavaan\\Engine\\',  'engines/');   // با resolve wildcard داخلی
$autoloader->register();

// ── ۵) مدیریت خطای زودهنگام ──────────────────────────────────────────────
EarlyErrorManager::register();

// ── ۶) بارگذاری نگاشت کامل از config (اگر موجود) ─────────────────────────
$configFile = TVN_BASE_PATH . '/config/autoload.php';
if (\is_file($configFile)) {
    $autoloadConfig = require $configFile;
    foreach ($autoloadConfig['psr-4'] ?? [] as $prefix => $paths) {
        $autoloader->addNamespace($prefix, $paths);
    }
    $classMapCache = TVN_BASE_PATH . '/' . ($autoloadConfig['cache']['file'] ?? 'storage/cache/classmap.php');
    if (($autoloadConfig['cache']['enabled'] ?? false) && \is_file($classMapCache)) {
        $autoloader->addClassMap(require $classMapCache);
    }
    foreach ($autoloadConfig['files'] ?? [] as $file) {
        $autoloader->addFile($file);
    }
}

// ── ۷) در دسترس قرار دادن Autoloader ─────────────────────────────────────
\Tavaan\Core\Autoloading\AutoloaderRegistry::set($autoloader);

return $autoloader;
```

---

## ۱۱. معیار پذیرش 🧪

- AC-BOOT-01: پروژه بدون پوشهٔ `vendor/` بالا می‌آید و صفحهٔ خوش‌آمد نمایش داده می‌شود.
- AC-BOOT-02: `Autoloader` همهٔ کلاس‌های `core/`, `engines/*/src/`, `modules/*/src/` را پیدا می‌کند.
- AC-BOOT-03: تلاش برای بارگذاری کلاس با نام `..\..\etc` → `AutoloadException`.
- AC-BOOT-04: ClassMap Cache در PROD زمان Load را ≥ ۷۰٪ کاهش می‌دهد.
- AC-BOOT-05: `.env` با مقادیر نقل‌قول‌دار، کامنت درون‌خطی و `true/false/null` درست پارس می‌شود.
- AC-BOOT-06: `putenv` هیچ‌جا فراخوانی نمی‌شود (بررسی AST).
- AC-BOOT-07: در PROD با `app.debug=true` → بوت شکست می‌خورد.
- AC-BOOT-08: زمان بوت کامل (P1..P6) ≤ ۸ms با Cache و opcache.
- AC-BOOT-09: Bootstrapperها idempotent هستند (دو بار اجرا → یک اثر).
- AC-BOOT-10: `tvn optimize` همهٔ فایل‌های کش را تولید و `optimize:clear` حذف می‌کند.

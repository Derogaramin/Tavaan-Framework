<?php

declare(strict_types=1);

namespace Tavaan\Core\Autoloading;

/**
 * تَوان | Tavaan — Core
 * Autoloader — بارگذار خودکار کلاس‌ها، بدون Composer (ADR-0005).
 *
 * قابلیت‌ها:
 * - PSR-4 (چند مسیره) + ClassMap (O(1)) + Wildcard (`engines/*&#47;src/`)
 * - Prefix Length Sorting (دقیق‌ترین نگاشت اول) — AL-07
 * - امنیت: رد نام کلاس ناامن، بررسی containment در basePath، Lock روی prefixهای هسته
 * - کش ClassMap برای PROD (dumpClassMap) — AL-03/AL-10
 *
 * این فایل تنها فایلی است که در `core/bootstrap.php` به‌صورت دستی require می‌شود؛
 * بنابراین **هیچ وابستگی** به سایر کلاس‌های فریمورک ندارد (به‌جز AutoloadException).
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class Autoloader
{
    /** حداکثر طول مجاز نام کلاس (DoS protection) */
    private const MAX_CLASS_LENGTH = 255;

    /** @var array<string, list<string>> prefix → فهرست دایرکتوری‌ها (PSR-4) */
    private array $prefixes = [];

    /** @var array<string, list<string>> prefix → فهرست الگوهای wildcard (حاوی `*`) */
    private array $wildcards = [];

    /** @var array<string, string> FQCN → مسیر فایل */
    private array $classMap = [];

    /** 🔒 ClassMap معتبر (کش تولیدشده) → بدون is_file؛ entré کهنه با scan جبران می‌شود */
    private bool $classMapAuthoritative = false;

    /** @var list<string> فایل‌هایی که باید یک‌بار require شوند */
    private array $files = [];

    /** @var array<string, true> فایل‌های require شده (idempotent) */
    private array $loadedFiles = [];

    /** @var array<string, true> کلاس‌های بارگذاری‌شده */
    private array $loaded = [];

    /** @var array<string, true> prefixهای قفل‌شده (غیرقابل override) */
    private array $lockedPrefixes = [];

    /** @var array<string, string> کش مسیر دایرکتوری wildcard */
    private array $wildcardCache = [];

    private bool $registered = false;

    private int $classMapHits = 0;

    private int $prefixHits = 0;

    private int $wildcardHits = 0;

    private int $misses = 0;

    private string $basePath;

    private function __construct(string $basePath)
    {
        $resolved = \realpath($basePath);
        $this->basePath = \rtrim($resolved === false ? $basePath : $resolved, '/\\');
    }

    public static function create(string $basePath): self
    {
        return new self($basePath);
    }

    // ─── ثبت/لغو ──────────────────────────────────────────────────────────

    public function register(bool $prepend = true): void
    {
        if ($this->registered) {
            return;
        }

        \spl_autoload_register($this->loadClass(...), prepend: $prepend);
        $this->registered = true;
        $this->loadFiles();
    }

    public function unregister(): void
    {
        if (!$this->registered) {
            return;
        }

        \spl_autoload_unregister($this->loadClass(...));
        $this->registered = false;
    }

    public function isRegistered(): bool
    {
        return $this->registered;
    }

    // ─── نگاشت ────────────────────────────────────────────────────────────

    /**
     * افزودن نگاشت PSR-4؛ مسیرهای حاوی `*` به‌عنوان wildcard ثبت می‌شوند.
     *
     * @param string|list<string> $paths
     */
    public function addNamespace(string $prefix, string|array $paths, bool $prepend = false): void
    {
        $prefix = $this->normalizePrefix($prefix);

        if (isset($this->lockedPrefixes[$prefix]) && isset($this->prefixes[$prefix])) {
            return;   // 🔒 prefix قفل‌شده غیرقابل override
        }

        foreach ((array) $paths as $path) {
            $normalized = $this->normalizePath((string) $path);

            if (\str_contains($normalized, '*')) {
                $this->addToGroup($this->wildcards, $prefix, $normalized, $prepend);
                continue;
            }
            $this->addToGroup($this->prefixes, $prefix, $normalized, $prepend);
        }

        $this->sortPrefixesByLength();
    }

    /** قفل‌کردن prefix (ثبت بعدی نادیده گرفته می‌شود). */
    public function lockPrefix(string $prefix): void
    {
        $this->lockedPrefixes[$this->normalizePrefix($prefix)] = true;
    }

    /**
     * @param array<string, string> $map
     * @param bool                  $authoritative اگر true، ورودی‌ها بدون is_file باور می‌شوند (O(1) واقعی)
     */
    public function addClassMap(array $map, bool $authoritative = false): void
    {
        foreach ($map as $class => $file) {
            $this->classMap[(string) $class] = (string) $file;
        }
        if ($authoritative) {
            $this->classMapAuthoritative = true;
        }
    }

    public function addFile(string $path): void
    {
        $normalized = $this->normalizePath($path);
        if (!isset($this->loadedFiles[$normalized])) {
            $this->files[] = $normalized;
        }
    }

    /** require فایل‌های ثبت‌شده (یک‌بار؛ idempotent). */
    public function loadFiles(): void
    {
        foreach ($this->files as $file) {
            if (isset($this->loadedFiles[$file])) {
                continue;
            }
            if (!\is_file($file)) {
                throw AutoloadException::classNotDeclared('(file)', $file);
            }
            require $file;
            $this->loadedFiles[$file] = true;
        }
    }

    // ─── بارگذاری ─────────────────────────────────────────────────────────

    /**
     * تلاش برای بارگذاری کلاس ( SPL autoloader callback ).
     *
     * @throws AutoloadException نام ناامن/فرار از basePath/کلاس declare نشده
     */
    public function loadClass(string $class): bool
    {
        if (isset($this->loaded[$class])) {
            return true;
        }

        // AL-05: کلاس قبلاً declare شده → بدون require
        if ($this->isDeclared($class)) {
            $this->loaded[$class] = true;

            return true;
        }

        $file = $this->findFile($class);
        if ($file !== null && $this->includeFile($file, $class)) {
            return true;
        }

        // ورودی کهنهٔ ClassMap معتبر → fallback به اسکن prefix/wildcard
        if ($file !== null) {
            $file = $this->findFileByScan($class);
            if ($file !== null && $this->includeFile($file, $class)) {
                return true;
            }
        }

        $this->misses++;

        return false;   // اجازه به autoloader بعدی
    }

    /**
     * یافتن مسیر فایل یک کلاس بدون بارگذاری (API عمومی — مانند Composer).
     *
     * ترتیب: ClassMap (O(1)) → PSR-4 Prefix (طولانی‌ترین اول) → Wildcard.
     *
     * @throws AutoloadException نام کلاس ناامن
     */
    public function findFile(string $class): ?string
    {
        $this->assertSafeClassName($class);

        // 1) ClassMap — سریع‌ترین مسیر O(1) (authoritative: بدون stat)
        if (isset($this->classMap[$class])) {
            $mapped = $this->classMap[$class];
            if ($this->classMapAuthoritative) {
                $this->classMapHits++;

                return $mapped;
            }
            if (\is_file($mapped)) {
                $this->classMapHits++;

                return $mapped;
            }
        }

        return $this->findFileByScan($class);
    }

    /**
     * اسکن PSR-4 Prefix و Wildcard (بدون ClassMap).
     */
    private function findFileByScan(string $class): ?string
    {
        // 2) PSR-4 Prefix (مرتب‌شده بر اساس طول — دقیق‌ترین اول)
        foreach ($this->prefixes as $prefix => $dirs) {
            if (!\str_starts_with($class, $prefix)) {
                continue;
            }
            $relativePath = \str_replace('\\', '/', \substr($class, \strlen($prefix))) . '.php';
            foreach ($dirs as $dir) {
                $file = $dir . '/' . $relativePath;
                if (\is_file($file)) {
                    $this->prefixHits++;

                    return $file;
                }
            }
        }

        // 3) Wildcard (`Tavaan\Engine\*` → engines/<kebab>/src/)
        $file = $this->findViaWildcard($class);
        if ($file !== null) {
            $this->wildcardHits++;

            return $file;
        }

        return null;
    }

    // ─── ClassMap Cache ───────────────────────────────────────────────────

    /**
     * تولید ClassMap کامل برای کش (توسط `tvn autoload:cache`).
     *
     * @param list<string> $exclude الگوهای حذف (مانند `tests/`, `docs/`)
     *
     * @return array<string, string>
     */
    public function dumpClassMap(?string $outputFile = null, array $exclude = []): array
    {
        $generator = new ClassMapGenerator($exclude);
        $map = $generator->generate($this->prefixes, $this->wildcards, $this->basePath);

        if ($outputFile !== null) {
            ClassMapGenerator::writePhpArray($outputFile, $map);
        }

        return $map;
    }

    // ─── introspection ────────────────────────────────────────────────────

    /** @return list<string> */
    public function loadedClasses(): array
    {
        return \array_keys($this->loaded);
    }

    /** @return array<string, list<string>> */
    public function prefixes(): array
    {
        return $this->prefixes;
    }

    /** @return array<string, list<string>> */
    public function wildcardPrefixes(): array
    {
        return $this->wildcards;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function stats(): AutoloaderStats
    {
        return new AutoloaderStats(
            classMapHits: $this->classMapHits,
            prefixHits: $this->prefixHits,
            wildcardHits: $this->wildcardHits,
            misses: $this->misses,
            loadedFiles: \count($this->loadedFiles),
        );
    }

    // ─── خصوصی ───────────────────────────────────────────────────────────

    private function isDeclared(string $class): bool
    {
        return \class_exists($class, false)
            || \interface_exists($class, false)
            || \trait_exists($class, false)
            || \enum_exists($class, false);
    }

    private function findViaWildcard(string $class): ?string
    {
        foreach ($this->wildcards as $prefix => $patterns) {
            if (!\str_starts_with($class, $prefix)) {
                continue;
            }

            $segments = \explode('\\', \substr($class, \strlen($prefix)));
            $first = \array_shift($segments);
            if ($first === null || $first === '' || $segments === []) {
                continue;
            }

            $kebab = $this->kebab($first);
            foreach ($patterns as $pattern) {
                $cacheKey = $prefix . '|' . $pattern . '|' . $kebab;
                $dir = $this->wildcardCache[$cacheKey] ??= $this->normalizePath(\str_replace('*', $kebab, $pattern));

                $file = $dir . '/' . \implode('/', $segments) . '.php';
                if (\is_file($file)) {
                    return $file;
                }
            }
        }

        return null;
    }

    private function includeFile(string $file, string $class): bool
    {
        // AL-05: کلاس قبلاً (توسط هر loader دیگری) declare شده → require مجدد ممنوع
        if ($this->isDeclared($class)) {
            $this->loaded[$class] = true;

            return true;
        }

        if (!\is_file($file)) {
            return false;
        }

        $real = \realpath($file);
        if ($real === false) {
            return false;
        }
        $this->assertInsideBasePath($real);

        require $real;

        if (!$this->isDeclared($class)) {
            throw AutoloadException::classNotDeclared($class, $real);
        }

        $this->loaded[$class] = true;
        $this->loadedFiles[$real] = true;

        return true;
    }

    /**
     * 🔒 امنیت: نام کلاس فقط [A-Za-z0-9_\] و حداکثر ۲۵۵ کاراکتر.
     */
    private function assertSafeClassName(string $class): void
    {
        if ($class === '' || \strlen($class) > self::MAX_CLASS_LENGTH || \preg_match('#[^A-Za-z0-9_\\\\]#', $class) === 1) {
            throw AutoloadException::unsafeClassName($class);
        }
    }

    /** 🔒 امنیت: فایل باید داخل basePath باشد. */
    private function assertInsideBasePath(string $realPath): void
    {
        if ($this->basePath !== '' && !\str_starts_with($realPath, $this->basePath)) {
            throw AutoloadException::pathEscape($realPath);
        }
    }

    private function normalizePrefix(string $prefix): string
    {
        $prefix = \trim($prefix, '\\');

        return $prefix === '' ? '' : $prefix . '\\';
    }

    private function normalizePath(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        if ($path === '') {
            return $this->basePath;
        }
        if ($path[0] !== '/' && !\preg_match('#^[A-Za-z]:/#', $path)) {
            $path = $this->basePath . '/' . $path;
        }

        return \rtrim($path, '/');
    }

    /** مرتب‌سازی نزولی بر اساس طول prefix تا دقیق‌ترین اولویت یابد (AL-07). */
    private function sortPrefixesByLength(): void
    {
        $sorter = static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a);
        \uksort($this->prefixes, $sorter);
        \uksort($this->wildcards, $sorter);
    }

    /**
     * @param array<string, list<string>> $group
     */
    private function addToGroup(array &$group, string $prefix, string $path, bool $prepend): void
    {
        $existing = $group[$prefix] ?? [];
        if (\in_array($path, $existing, true)) {
            return;
        }
        $group[$prefix] = $prepend ? [$path, ...$existing] : [...$existing, $path];
    }

    /** PascalCase → kebab-case (بدون وابستگی به Str — این کلاس standalone است). */
    private function kebab(string $value): string
    {
        return \strtolower((string) \preg_replace('/(?<!^)[A-Z]/', '-$0', $value));
    }
}

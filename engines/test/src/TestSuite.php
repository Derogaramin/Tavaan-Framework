<?php

declare(strict_types=1);

namespace Tavaan\Testing;

/**
 * تَوان | Tavaan — Testing
 * TestSuite — کشف و نگه‌داری مجموعهٔ تست‌ها (فایل → کلاس → متد + گروه‌ها).
 *
 * کشف: فایل‌های `*Test.php` در مسیرهای داده‌شده → کلاس‌های غیرانتزاعی
 * `TestCase` → متدهای عمومی `test*`. گروه‌های ضمنی از مسیر استخراج می‌شوند
 * (slug موتور + `unit`/`feature`/`app`) و Attributeهای `#[Group]` به آن‌ها
 * افزوده می‌شوند. ترتیب کشف قطعی است (sort — Deterministic).
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class TestSuite
{
    /** @param list<TestClassDescriptor> $classes */
    private function __construct(private readonly array $classes)
    {
    }

    /**
     * کشف Suite از فهرست مسیرها (فایل یا دایرکتوری).
     *
     * @param list<string> $paths
     */
    public static function discover(array $paths): self
    {
        return self::fromFiles(self::discoverFiles($paths));
    }

    /**
     * ساخت Suite از فهرست صریح فایل‌ها (حالت موازی/manifest).
     *
     * @param list<string> $files
     */
    public static function fromFiles(array $files): self
    {
        $classes = [];

        foreach ($files as $file) {
            $implicitGroups = self::implicitGroups($file);

            foreach (self::loadTestClasses($file) as $class) {
                $reflection = new \ReflectionClass($class);
                $classGroups = [...$implicitGroups, ...self::attributeGroups($reflection->getAttributes(Group::class))];

                $methods = [];
                foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    if (!\str_starts_with($method->getName(), 'test')) {
                        continue;
                    }
                    $methods[] = new TestMethodDescriptor(
                        name: $method->getName(),
                        groups: [...$classGroups, ...self::attributeGroups($method->getAttributes(Group::class))],
                    );
                }

                if ($methods !== []) {
                    $classes[] = new TestClassDescriptor($class, $file, $methods);
                }
            }
        }

        return new self($classes);
    }

    /** @return list<TestClassDescriptor> */
    public function classes(): array
    {
        return $this->classes;
    }

    /** @return list<string> */
    public function files(): array
    {
        $files = [];
        foreach ($this->classes as $class) {
            $files[$class->file] = true;
        }

        return \array_keys($files);
    }

    public function countClasses(): int
    {
        return \count($this->classes);
    }

    public function countMethods(): int
    {
        $count = 0;
        foreach ($this->classes as $class) {
            $count += \count($class->methods);
        }

        return $count;
    }

    /**
     * باریک‌کردن Suite با فیلتر نام (کلاس/متد — case-insensitive) و گروه.
     */
    public function filter(?string $filter = null, ?string $group = null): self
    {
        if ($filter === null && $group === null) {
            return $this;
        }

        $needle = $filter === null ? null : \strtolower($filter);
        $classes = [];

        foreach ($this->classes as $class) {
            $methods = [];
            foreach ($class->methods as $method) {
                $fullName = \strtolower($class->shortName() . '::' . $method->name);
                if ($needle !== null
                    && !\str_contains($fullName, $needle)
                    && !\str_contains(\strtolower($class->class), $needle)) {
                    continue;
                }
                if ($group !== null && !\in_array(\strtolower($group), $method->groups, true)) {
                    continue;
                }
                $methods[] = $method;
            }

            if ($methods !== []) {
                $classes[] = new TestClassDescriptor($class->class, $class->file, $methods);
            }
        }

        return new self($classes);
    }

    /**
     * تقسیم فایل‌ها به N دسته (round-robin روی فهرست مرتب — قطعی) برای اجرای موازی.
     *
     * @return list<list<string>>
     */
    public function chunkFiles(int $chunks): array
    {
        $chunks = \max(1, $chunks);
        $result = \array_fill(0, $chunks, []);

        $files = $this->files();
        \sort($files);
        foreach (\array_values($files) as $index => $file) {
            $result[$index % $chunks][] = $file;
        }

        return \array_values(\array_filter($result, static fn (array $chunk): bool => $chunk !== []));
    }

    /**
     * کشف فایل‌های `*Test.php` از مسیرها (بازگشتی؛ ترتیب قطعی).
     *
     * @param list<string> $paths
     *
     * @return list<string>
     */
    public static function discoverFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (\is_file($path)) {
                $files[] = $path;
                continue;
            }
            if (!\is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            );
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && \str_ends_with($file->getFilename(), 'Test.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        $files = \array_values(\array_unique($files));
        \sort($files);   // 🔒 ترتیب قطعی

        return $files;
    }

    /**
     * گروه‌های ضمنی از مسیر فایل.
     *
     * `engines/cache/tests/Feature/CacheTest.php` → `['cache', 'feature']`
     * `tests/Unit/AutoloaderTest.php` → `['app', 'unit']`
     *
     * @return list<string>
     */
    private static function implicitGroups(string $file): array
    {
        $normalized = \strtolower(\str_replace('\\', '/', $file));
        $groups = [];

        if (\preg_match('#/engines/([a-z0-9\-]+)/tests/#', $normalized, $match) === 1) {
            $groups[] = $match[1];
        } elseif (\preg_match('#/tests/(unit|feature)/#', $normalized) === 1) {
            $groups[] = 'app';
        }

        if (\str_contains($normalized, '/tests/unit/')) {
            $groups[] = 'unit';
        } elseif (\str_contains($normalized, '/tests/feature/')) {
            $groups[] = 'feature';
        } elseif (\str_contains($normalized, '/tests/security/')) {
            $groups[] = 'security';
        }

        return $groups;
    }

    /**
     * @param list<\ReflectionAttribute<Group>> $attributes
     *
     * @return list<string>
     */
    private static function attributeGroups(array $attributes): array
    {
        $groups = [];
        foreach ($attributes as $attribute) {
            $groups[] = \strtolower($attribute->newInstance()->name);
        }

        return $groups;
    }

    /**
     * بارگذاری کلاس‌های تستِ اعلام‌شده در یک فایل.
     *
     * کشف با پارس token انجام می‌شود (نه diff کلاس‌های declare‌شده) →
     * include مجدد یک فایل در همان فرایند هم کلاس‌ها را برمی‌گرداند
     * (idempotent — لازم برای discoverهای مکرر و فرزندِ موازی).
     *
     * @return list<class-string<TestCase>>
     */
    private static function loadTestClasses(string $file): array
    {
        require_once $file;

        $classes = [];
        foreach (self::parseClassNames($file) as $class) {
            if (!\class_exists($class, false)) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            if ($reflection->isSubclassOf(TestCase::class) && !$reflection->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * استخراج نام FQCN کلاس‌های نام‌دارِ فایل (بدون `::class` و anonymous).
     *
     * @return list<class-string>
     */
    private static function parseClassNames(string $file): array
    {
        $contents = \file_get_contents($file);
        if (!\is_string($contents) || $contents === '') {
            return [];
        }

        $namespace = '';
        $expectNamespace = false;
        $expectClassName = false;
        $prevWasDoubleColon = false;
        $prevWasNew = false;
        $classes = [];

        foreach (\token_get_all($contents) as $token) {
            if (\is_string($token)) {
                if ($expectNamespace && ($token === ';' || $token === '{')) {
                    $expectNamespace = false;
                }
                $prevWasDoubleColon = false;
                $prevWasNew = false;
                continue;
            }

            [$id, $text] = $token;
            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }

            if ($expectNamespace) {
                if (\in_array($id, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                    $namespace = \ltrim($text, '\\');
                    $expectNamespace = false;
                    $prevWasDoubleColon = false;
                    $prevWasNew = false;
                    continue;
                }
                $expectNamespace = false;   // `namespace { … }` → فضای‌نام سراسری
            }

            if ($expectClassName && $id === T_STRING) {
                $classes[] = $namespace === '' ? $text : $namespace . '\\' . $text;
                $expectClassName = false;
            } elseif ($id === T_NAMESPACE) {
                $expectNamespace = true;
                $expectClassName = false;
            } elseif ($id === T_CLASS) {
                $expectClassName = !$prevWasDoubleColon && !$prevWasNew;
            } elseif (\in_array($id, [T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $expectClassName = false;
            }

            $prevWasDoubleColon = $id === T_DOUBLE_COLON;
            $prevWasNew = $id === T_NEW;
        }

        return $classes;
    }
}

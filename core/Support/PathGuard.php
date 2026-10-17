<?php

declare(strict_types=1);

namespace Tavaan\Support;

/**
 * تَوان | Tavaan — Support
 * PathGuard — بررسی containment مسیر به‌صورت cross-platform (Linux/macOS/Windows).
 *
 * چرا: `realpath` در ویندوز backslash و حرف درایو (`C:`) برمی‌گرداند و
 * مقایسهٔ سادهٔ `str_starts_with` با `/` شکست می‌خورد؛ همچنین مسیر فایل
 * *وجود‌نداشته* باید با نزدیک‌ترین جدِ موجود سنجیده شود (probe).
 *
 * 🔒 قاعدهٔ containment با separator انتهایی مقایسه می‌شود تا base مثل
 * `/app` اشتباهاً `/application` را شامل نشود.
 *
 * @api 1.0
 * @see docs/01-standards/security-policy.md
 */
final class PathGuard
{
    private function __construct()
    {
    }

    /** آیا مسیر مطلق است؟ (`/…`، `\\server\share`، `C:\…`، `C:/…`) */
    public static function isAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return \preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * نرمال‌سازی برای مقایسه: یکدست‌سازی separator + (در ویندوز) lowercase
     * چون NTFS case-insensitive است.
     */
    public static function normalize(string $path): string
    {
        $path = \str_replace('/', \DIRECTORY_SEPARATOR, $path);
        if (\PHP_OS_FAMILY === 'Windows') {
            $path = \strtolower(\str_replace('\\', \DIRECTORY_SEPARATOR, $path));
        }

        return $path;
    }

    /** آیا مسیر (مطلق و resolve شده) داخل base است؟ (خودِ base هم قبول است) */
    public static function contains(string $base, string $path): bool
    {
        $base = \rtrim(self::normalize($base), \DIRECTORY_SEPARATOR);
        $path = self::normalize($path);

        if ($base === '') {
            return false;
        }

        return $path === $base || \str_starts_with($path, $base . \DIRECTORY_SEPARATOR);
    }

    /** نزدیک‌ترین جدِ موجودِ مسیر (یا خود مسیر اگر موجود است). */
    public static function existingAncestor(string $path): ?string
    {
        $probe = $path;
        for ($i = 0; $i < 64; $i++) {
            if ($probe === '' || \file_exists($probe)) {
                return $probe === '' ? null : $probe;
            }
            $parent = \dirname($probe);
            if ($parent === $probe) {
                break;
            }
            $probe = $parent;
        }

        return null;
    }

    /**
     * آیا مسیر (حتی اگر خودش هنوز وجود ندارد) داخل base می‌ماند؟
     *
     * برای فایل وجود‌نداشته، نزدیک‌ترین جدِ موجود مبناست — در ویندوز
     * `/etc/passwd` به ریشهٔ درایو می‌رسد و بیرون از base تشخیص داده می‌شود.
     */
    public static function isInside(string $path, string $base): bool
    {
        $realBase = \realpath($base);
        if ($realBase === false) {
            return false;
        }

        $real = \realpath($path);
        if ($real === false) {
            $ancestor = self::existingAncestor($path);
            $real = $ancestor === null ? false : \realpath($ancestor);
        }
        if ($real === false) {
            return false;
        }

        return self::contains($realBase, $real);
    }
}

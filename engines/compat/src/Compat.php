<?php

declare(strict_types=1);

namespace Tavaan\Engine\Compat;

use Tavaan\Core\Exceptions\Engine\EngineCapabilityException;

/**
 * تَوان | Tavaan — Compat
 * Compat — نقطهٔ مرکزی تشخیص افزونه‌ها و انتخاب Driver/Fallback (DOC-083E).
 *
 * 🔒 کد موتورها باید `Compat::xxx()` را صدا بزنند؛ تشخیص و Fallback اینجا متمرکز است.
 *
 * @api 1.0
 * @see docs/03-engines/compat.md
 */
final class Compat
{
    /** @var array<string, string> قابلیت → درایور انتخاب‌شده (کش) */
    private static array $driverCache = [];

    private static bool $forceFallbacks = false;

    private function __construct()
    {
    }

    public static function has(string $extension): bool
    {
        return !self::$forceFallbacks && \extension_loaded($extension);
    }

    /** @param list<string> $extensions */
    public static function hasAny(array $extensions): bool
    {
        foreach ($extensions as $ext) {
            if (self::has($ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * الزام وجود افزونه — در غیر این صورت استثنا با fallback hint.
     *
     * @throws EngineCapabilityException
     */
    public static function require(string $extension, string $capability): void
    {
        if (!self::has($extension)) {
            throw new EngineCapabilityException(
                messageId: 'engine.capability_missing',
                context: ['extension' => $extension, 'capability' => $capability],
                hint: \sprintf('افزونهٔ php-%s را نصب کنید یا از fallback استفاده نمایید.', $extension),
            );
        }
    }

    /**
     * انتخاب درایور یک قابلیت:
     * `math` → bcmath|decimal — `zip` → zip|pure — `http` → curl|stream —
     * `image` → imagick|gd|none — `unicode` → intl|mbstring|native —
     * `mime` → fileinfo|magic.
     */
    public static function driver(string $capability): string
    {
        if (isset(self::$driverCache[$capability])) {
            return self::$driverCache[$capability];
        }

        return self::$driverCache[$capability] = match ($capability) {
            'math'    => self::has('bcmath') ? 'bcmath' : (self::has('gmp') ? 'gmp' : 'decimal'),
            'zip'     => self::has('zip') ? 'zip' : 'pure',
            'http'    => self::has('curl') ? 'curl' : 'stream',
            'image'   => self::has('imagick') ? 'imagick' : (self::has('gd') ? 'gd' : 'none'),
            'unicode' => self::has('intl') ? 'intl' : (self::has('mbstring') ? 'mbstring' : 'native'),
            'mime'    => self::has('fileinfo') ? 'fileinfo' : 'magic',
            'random'  => 'native',
            default   => 'none',
        };
    }

    /**
     * گزارش وضعیت افزونه‌ها + fallback فعال (برای `tvn doctor`).
     *
     * @return array<string, array{required: bool, loaded: bool, fallback: string}>
     */
    public static function report(): array
    {
        $extensions = [
            'mbstring' => 'unicode:native', 'bcmath' => 'math:decimal', 'gmp' => 'math:decimal',
            'curl' => 'http:stream', 'zip' => 'zip:pure', 'gd' => 'image:none',
            'imagick' => 'image:gd', 'intl' => 'unicode:mbstring', 'fileinfo' => 'mime:magic',
            'redis' => 'cache:file', 'apcu' => 'cache:file', 'memcached' => 'cache:file',
            'pdo_mysql' => 'db:none', 'pdo_pgsql' => 'db:none', 'pdo_sqlite' => 'db:none',
            'openssl' => 'crypt:none', 'sodium' => 'crypt:openssl', 'sockets' => 'realtime:stream',
            'pcntl' => 'queue:polling', 'posix' => 'console:getmypid', 'exif' => 'media:none',
            'opcache' => 'optimizer:none', 'sysvmsg' => 'queue:none',
        ];

        $report = [];
        foreach ($extensions as $ext => $fallback) {
            $loaded = \extension_loaded($ext);
            $report[$ext] = [
                'required' => \in_array($ext, ['openssl', 'json', 'hash', 'mbstring', 'pdo'], true),
                'loaded'   => $loaded && !self::$forceFallbacks,
                'fallback' => $fallback,
            ];
        }

        return $report;
    }

    /** اجبار fallbackها (تست AC-CMP-02). */
    public static function forceFallbacks(bool $force = true): void
    {
        self::$forceFallbacks = $force;
        self::$driverCache = [];
    }

    public static function isForcingFallbacks(): bool
    {
        return self::$forceFallbacks;
    }

    /** نسخهٔ PHP جاری. */
    public static function version(): string
    {
        return PHP_VERSION;
    }

    /** json_validate (8.3) با fallback. */
    public static function jsonValidate(string $json): bool
    {
        if (\function_exists('json_validate')) {
            return \json_validate($json);
        }

        \json_decode($json);

        return \json_last_error() === JSON_ERROR_NONE;
    }
}

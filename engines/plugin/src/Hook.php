<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin;

use Tavaan\Engine\Plugin\Exceptions\PluginException;

/**
 * تَوان | Tavaan — Plugin
 * Hook — نقطهٔ اتصال افزونه‌ها به میزبان (DOC-079 §6).
 *
 * - میزبان hookها را در `module.json → hooks` یا `config/plugins.php → allowed_hooks`
 *   اعلام می‌کند (`Hook::declare` توسط PluginServiceProvider انجام می‌شود).
 * - پلاگین **فقط** به hook اعلام‌شده می‌تواند متصل شود (pluginId != null).
 * - fire() خطای پلاگین را ایزوله می‌کند (Throwable جمع می‌شود، میزبان نمی‌شکند)
 *   و با timeout/memory همکاری‌کننده از DoS جلوگیری می‌کند (§9).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class Hook
{
    /** @var array<string, list<array{listener: callable, plugin: ?string}>> */
    private static array $listeners = [];

    /** @var array<string, true> hookهای اعلام‌شدهٔ میزبان */
    private static array $declared = [];

    /** @var list<array{hook: string, plugin: ?string, reason: string}> */
    private static array $violations = [];

    private static int $timeoutMs = 5000;

    private static ?int $memoryLimitBytes = null;

    private function __construct()
    {
    }

    // ─── اعلام و ثبت ──────────────────────────────────────────────────────

    /** اعلام hookهای میزبان (module.json / config). */
    public static function declare(string ...$hooks): void
    {
        foreach ($hooks as $hook) {
            if ($hook !== '') {
                self::$declared[$hook] = true;
            }
        }
    }

    public static function declared(string $hook): bool
    {
        return isset(self::$declared[$hook]);
    }

    /**
     * ثبت listener — برای پلاگین‌ها فقط hookهای اعلام‌شده مجاز است.
     *
     * @throws PluginException اگر hook برای پلاگین اعلام نشده باشد
     */
    public static function listen(string $hook, callable $listener, ?string $pluginId = null): void
    {
        if ($pluginId !== null && !isset(self::$declared[$hook])) {
            throw PluginException::hookNotDeclared($hook, $pluginId);
        }

        self::$listeners[$hook][] = ['listener' => $listener, 'plugin' => $pluginId];
    }

    public static function has(string $hook): bool
    {
        return isset(self::$listeners[$hook]) && self::$listeners[$hook] !== [];
    }

    // ─── اجرا ─────────────────────────────────────────────────────────────

    /**
     * اجرای همهٔ listenerهای یک hook (ترتیب ثبت — قطعی).
     *
     * خطای پلاگین ایزوله می‌شود: نتیجهٔ آن جایگاه، نمونهٔ `Throwable` است.
     * عبور از timeout/memory → بقیهٔ listenerهای همان hook اجرا نمی‌شوند
     * (enforcement همکاری‌کننده — بدون pcntl).
     *
     * @return list<mixed>
     */
    public static function fire(string $hook, mixed ...$args): array
    {
        $results = [];

        foreach (self::$listeners[$hook] ?? [] as $entry) {
            $startedAt = \microtime(true);
            $memoryBefore = \memory_get_usage();

            try {
                $results[] = ($entry['listener'])(...$args);
            } catch (\Throwable $e) {
                $results[] = $e;
            }

            $elapsedMs = (\microtime(true) - $startedAt) * 1000;
            if ($elapsedMs > self::$timeoutMs) {
                self::$violations[] = [
                    'hook' => $hook,
                    'plugin' => $entry['plugin'],
                    'reason' => \sprintf('timeout: %.0fms > %dms', $elapsedMs, self::$timeoutMs),
                ];
                break;
            }

            if (self::$memoryLimitBytes !== null
                && \memory_get_usage() - $memoryBefore > self::$memoryLimitBytes) {
                self::$violations[] = [
                    'hook' => $hook,
                    'plugin' => $entry['plugin'],
                    'reason' => 'memory limit exceeded',
                ];
                break;
            }
        }

        return $results;
    }

    // ─── مدیریت چرخهٔ عمر ─────────────────────────────────────────────────

    /** حذف listenerهای یک پلاگین (disable). */
    public static function flushPlugin(string $pluginId): void
    {
        foreach (self::$listeners as $hook => $entries) {
            self::$listeners[$hook] = \array_values(\array_filter(
                $entries,
                static fn (array $entry): bool => $entry['plugin'] !== $pluginId,
            ));
            if (self::$listeners[$hook] === []) {
                unset(self::$listeners[$hook]);
            }
        }
    }

    /** پاک‌سازی کامل (تست‌ها). */
    public static function flush(): void
    {
        self::$listeners = [];
        self::$declared = [];
        self::$violations = [];
    }

    /** @param list<array{hook: string, plugin: ?string, reason: string}> $violations */
    public static function setLimits(int $timeoutMs, ?int $memoryLimitBytes = null): void
    {
        self::$timeoutMs = \max(1, $timeoutMs);
        self::$memoryLimitBytes = $memoryLimitBytes;
    }

    /** @return list<array{hook: string, plugin: ?string, reason: string}> */
    public static function violations(): array
    {
        return self::$violations;
    }
}

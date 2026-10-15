<?php

declare(strict_types=1);

namespace Tavaan\Support;

/**
 * تَوان | Tavaan — Support
 * Arr — ابزار آرایه با دسترسی dot-notation.
 *
 * @api 1.0
 * @see docs/04-structure/folder-structure.md
 */
final class Arr
{
    private function __construct()
    {
    }

    /**
     * خواندن مقدار با کلید dot-path.
     *
     * @param array<array-key, mixed> $array
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (\array_key_exists($key, $array)) {
            return $array[$key];
        }

        $value = $array;
        foreach (\explode('.', $key) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * نوشتن مقدار با کلید dot-path (ساخت خودکار مسیر).
     *
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    public static function set(array &$array, string $key, mixed $value): array
    {
        $segments = \explode('.', $key);
        $target = &$array;
        while (\count($segments) > 1) {
            $segment = \array_shift($segments);
            if (!isset($target[$segment]) || !\is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }
        $target[\array_shift($segments)] = $value;

        return $array;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function has(array $array, string $key): bool
    {
        if (\array_key_exists($key, $array)) {
            return true;
        }

        $value = $array;
        foreach (\explode('.', $key) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    public static function forget(array &$array, string $key): void
    {
        $segments = \explode('.', $key);
        $last = \array_pop($segments);
        $target = &$array;
        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !\is_array($target[$segment])) {
                return;
            }
            $target = &$target[$segment];
        }
        unset($target[$last]);
    }

    /**
     * mask بازگشتی کلیدهای حساس (برای Config::masked).
     *
     * @param array<array-key, mixed> $data
     * @param list<string>            $patterns الگوهای glob-like با `*` (مانند `db.*.password`)
     *
     * @return array<array-key, mixed>
     */
    public static function maskRecursive(array $data, array $patterns, string $replacement = '********', string $path = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $current = $path === '' ? (string) $k : $path . '.' . $k;
            if (\is_array($v)) {
                $out[$k] = self::maskRecursive($v, $patterns, $replacement, $current);
                continue;
            }
            $out[$k] = self::matchesAny($current, $patterns) ? $replacement : $v;
        }

        return $out;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param list<string>            $keys
     *
     * @return array<string, mixed>
     */
    public static function only(array $array, array $keys): array
    {
        return \array_intersect_key($array, \array_flip($keys));
    }

    /**
     * @param array<array-key, mixed> $array
     * @param list<string>            $keys
     *
     * @return array<string, mixed>
     */
    public static function except(array $array, array $keys): array
    {
        return \array_diff_key($array, \array_flip($keys));
    }

    /**
     * ادغام عمیق: listها جایگزین، mapها ادغام (DOC-032 §5.3).
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $override
     *
     * @return array<array-key, mixed>
     */
    public static function mergeDeep(array $base, array $override): array
    {
        foreach ($override as $k => $v) {
            if (\is_array($v) && isset($base[$k]) && \is_array($base[$k])) {
                $base[$k] = \array_is_list($v) ? $v : self::mergeDeep($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }

        return $base;
    }

    /**
     * @param list<string> $patterns
     */
    private static function matchesAny(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $regex = '/^' . \str_replace('\*', '[^.]+', \preg_quote($pattern, '/')) . '$/';
            if (\preg_match($regex, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}

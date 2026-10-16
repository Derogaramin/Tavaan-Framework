<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\OpenApi;

/**
 * تَوان | Tavaan — Api
 * Yaml — emitter یامل خودنوشته (بدون ext-yaml) برای خروجی OpenAPI.
 *
 * پشتیبانی: map/list تودرتو، رشته (plain/quoted)، عدد، bool، null.
 * رشته‌های چندخطی یا دارای کاراکتر خاص → double-quoted با escape.
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
final class Yaml
{
    /** کلمات رزرو که حتی اگر رشته باشند باید quote شوند */
    private const RESERVED = [
        'true', 'false', 'null', 'yes', 'no', 'on', 'off', '~',
        'True', 'False', 'Null', 'Yes', 'No', 'On', 'Off',
        'TRUE', 'FALSE', 'NULL', 'YES', 'NO', 'ON', 'OFF',
    ];

    private function __construct()
    {
    }

    public static function emit(mixed $data): string
    {
        return self::emitValue($data, 0);
    }

    private static function emitValue(mixed $data, int $indent): string
    {
        if ($data instanceof \stdClass) {
            return self::pad($indent) . "{}\n";   // map خالی (OpenAPI: paths: {})
        }
        if (\is_array($data)) {
            if ($data === []) {
                return self::pad($indent) . "[]\n";
            }

            return \array_is_list($data)
                ? self::emitList($data, $indent)
                : self::emitMap($data, $indent);
        }

        return self::pad($indent) . self::scalar($data) . "\n";
    }

    /** @param array<array-key, mixed> $map */
    private static function emitMap(array $map, int $indent): string
    {
        $out = '';
        foreach ($map as $key => $value) {
            $keyStr = self::key((string) $key);

            if (\is_array($value) && $value !== []) {
                $out .= self::pad($indent) . $keyStr . ":\n";
                $out .= \array_is_list($value)
                    ? self::emitList($value, $indent + 2)
                    : self::emitMap($value, $indent + 2);
            } elseif (\is_array($value)) {
                $out .= self::pad($indent) . $keyStr . ": []\n";
            } elseif ($value instanceof \stdClass) {
                $out .= self::pad($indent) . $keyStr . ": {}\n";
            } else {
                $out .= self::pad($indent) . $keyStr . ': ' . self::scalar($value) . "\n";
            }
        }

        return $out;
    }

    /** @param list<mixed> $list */
    private static function emitList(array $list, int $indent): string
    {
        $out = '';
        foreach ($list as $item) {
            if (\is_array($item) && $item !== []) {
                if (\array_is_list($item)) {
                    // list داخل list: آیتم اول در همان خط «-»
                    $sub = self::emitList($item, $indent + 2);
                    $first = true;
                    foreach (\explode("\n", \rtrim($sub, "\n")) as $line) {
                        if ($line === '') {
                            continue;
                        }
                        if ($first) {
                            $out .= self::pad($indent) . '- ' . \ltrim($line) . "\n";
                            $first = false;
                        } else {
                            $out .= $line . "\n";
                        }
                    }
                } else {
                    // map داخل list: کلیدهای اول در همان خط «-»
                    $sub = self::emitMap($item, $indent + 2);
                    $lines = \explode("\n", \rtrim($sub, "\n"));
                    $first = true;
                    foreach ($lines as $line) {
                        if ($line === '') {
                            continue;
                        }
                        if ($first) {
                            $out .= self::pad($indent) . '- ' . \substr($line, $indent + 2) . "\n";
                            $first = false;
                        } else {
                            $out .= $line . "\n";
                        }
                    }
                }
            } elseif (\is_array($item)) {
                $out .= self::pad($indent) . "- []\n";
            } elseif ($item instanceof \stdClass) {
                $out .= self::pad($indent) . "- {}\n";
            } else {
                $out .= self::pad($indent) . '- ' . self::scalar($item) . "\n";
            }
        }

        return $out;
    }

    private static function key(string $key): string
    {
        // کلیدهای OpenAPI معمولاً ساده‌اند؛ در صورت نیاز quote می‌شوند
        if (\preg_match('/^[A-Za-z0-9_.$\/-]+$/', $key) === 1 && !\in_array($key, self::RESERVED, true)) {
            return $key;
        }

        return self::quote($key);
    }

    private static function scalar(mixed $value): string
    {
        if ($value instanceof \stdClass) {
            return '{}';
        }
        if ($value === null) {
            return 'null';
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_int($value)) {
            return (string) $value;
        }
        if (\is_float($value)) {
            if ($value === INF || $value === -INF || $value === NAN) {
                return self::quote((string) $value);
            }

            return \rtrim(\rtrim(\sprintf('%.10F', $value), '0'), '.') ?: '0.0';
        }
        if ($value instanceof \Stringable || !\is_string($value)) {
            return self::quote((string) $value);
        }

        // رشته: اگر امن باشد plain، وگرنه quoted
        if ($value === '' || self::needsQuotes($value)) {
            return self::quote($value);
        }

        return $value;
    }

    private static function needsQuotes(string $value): bool
    {
        if (\in_array($value, self::RESERVED, true)) {
            return true;
        }
        // عدد‌نما؟
        if (\is_numeric($value)) {
            return true;
        }
        // کاراکترهای خاص یا شروع‌کننده‌های معنادار
        if (\preg_match('/^[\s]|[\s]$/', $value) === 1) {
            return true;
        }
        if (\strpbrk($value, "\n\r\t\"'\\:#{}[]|>&*!%@`,?") !== false) {
            return true;
        }
        if (\str_starts_with($value, '- ') || $value === '-') {
            return true;
        }
        // کنترل‌کاراکترها
        return \preg_match('/[^\x{20}-\x{7E}\x{A0}-\x{10FFFF}]/u', $value) === 1;
    }

    private static function quote(string $value): string
    {
        $escaped = \str_replace(
            ["\\", '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $value,
        );

        return '"' . $escaped . '"';
    }

    private static function pad(int $indent): string
    {
        return \str_repeat(' ', $indent);
    }
}

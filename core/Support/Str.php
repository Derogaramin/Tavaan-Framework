<?php

declare(strict_types=1);

namespace Tavaan\Support;

/**
 * تَوان | Tavaan — Support
 * Str — ابزار رشته (تبدیل case، تولید رشتهٔ تصادفی امن).
 *
 * @api 1.0
 * @see docs/04-structure/folder-structure.md
 */
final class Str
{
    private function __construct()
    {
    }

    /** PascalCase → kebab-case (HttpClient → http-client) */
    public static function kebab(string $value): string
    {
        return \mb_strtolower((string) \preg_replace('/(?<!^)[A-Z]/', '-$0', $value));
    }

    /** kebab-case → PascalCase (http-client → HttpClient) */
    public static function pascal(string $value): string
    {
        return \str_replace(' ', '', \ucwords(\str_replace(['-', '_'], ' ', $value)));
    }

    /** PascalCase → camelCase */
    public static function camel(string $value): string
    {
        return \lcfirst(self::pascal($value));
    }

    /** PascalCase/camelCase → snake_case */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        return \mb_strtolower((string) \preg_replace('/(?<!^)[A-Z]/', $delimiter . '$0', $value));
    }

    public static function startsWith(string $haystack, string $needle): bool
    {
        return \str_starts_with($haystack, $needle);
    }

    public static function endsWith(string $haystack, string $suffix): bool
    {
        return \str_ends_with($haystack, $suffix);
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return \str_contains($haystack, $needle);
    }

    /** تولید رشتهٔ hex تصادفی امن (random_bytes). */
    public static function randomHex(int $bytes = 16): string
    {
        return \bin2hex(\random_bytes($bytes));
    }

    /** محدودسازی طول با برش امن UTF-8. */
    public static function limit(string $value, int $limit = 100, string $end = '…'): string
    {
        if (\mb_strlen($value) <= $limit) {
            return $value;
        }

        return \rtrim(\mb_substr($value, 0, $limit)) . $end;
    }
}

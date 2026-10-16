<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search;

/**
 * تَوان | Tavaan — Search
 * Highlighter — برجسته‌سازی واژه‌های منطبق در متن.
 *
 * 🔒 escape-then-mark (DOC-052 §7): اول همه‌چیز با htmlspecialchars
 * escape می‌شود، سپس فقط تگ‌های <mark> خودمان افزوده می‌شوند — بدون XSS.
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class Highlighter
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $queryTokens توکن‌های نرمال‌شده+stem‌شدهٔ کوئری
     */
    public static function highlight(
        string $text,
        array $queryTokens,
        string $openTag = '<mark>',
        string $closeTag = '</mark>',
    ): string {
        if ($text === '' || $queryTokens === []) {
            return self::escape($text);
        }

        // 🔒 تگ‌ها فقط از فراخوان داخلی می‌آیند؛ برای اطمینان به‌صورت جفت
        // اعتبارسنجی می‌شوند — اگر هر یک نامعتبر بود، جفت پیش‌فرض استفاده می‌شود.
        if (self::isSimpleTag($openTag) && self::isSimpleTag($closeTag)) {
            // معتبر
        } else {
            $openTag = '<mark>';
            $closeTag = '</mark>';
        }

        $lookup = [];
        foreach ($queryTokens as $token) {
            $lookup[$token] = true;
        }

        $parts = \preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (\preg_match('/^\s+$/u', $part) === 1) {
                $out .= self::escape($part);

                continue;
            }

            $escaped = self::escape($part);
            if (self::matches($part, $lookup)) {
                $out .= $openTag . $escaped . $closeTag;
            } else {
                $out .= $escaped;
            }
        }

        return $out;
    }

    /** @param array<string, true> $lookup */
    private static function matches(string $word, array $lookup): bool
    {
        // واژه با چسبیده‌های نشانه‌گذاری مقایسه می‌شود: «کتاب،» → «كتاب»
        $clean = (string) (\preg_replace('/[^\p{L}\p{N}]+/u', '', $word) ?? '');
        if ($clean === '') {
            return false;
        }
        $normalized = \mb_strtolower(Persian::normalize($clean));
        if (isset($lookup[$normalized])) {
            return true;
        }

        return isset($lookup[Persian::stem($normalized)]);
    }

    private static function escape(string $text): string
    {
        return \htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** فقط تگ سادهٔ بدون attribute مجاز است. */
    private static function isSimpleTag(string $tag): bool
    {
        return \preg_match('#^</?[a-zA-Z][a-zA-Z0-9]*>$#', $tag) === 1;
    }
}

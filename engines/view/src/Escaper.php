<?php

declare(strict_types=1);

namespace Tavaan\Engine\View;

/**
 * تَوان | Tavaan — View
 * Escaper — escape زمینه‌ای HTML/Attr/JS/URL/CSS (DOC-043 §5).
 *
 * 🔒 `{{ }}` همیشه از اینجا عبور می‌کند — مگر SafeString.
 *
 * @api 1.0
 * @see docs/03-engines/view.md
 */
final class Escaper
{
    private function __construct()
    {
    }

    public static function html(mixed $value): string
    {
        // مسیر سریع: رشتهٔ معمول (اکثر فراخوانی‌ها)
        if (\is_string($value)) {
            if ($value === '') {
                return '';
            }
            // fast path: strpos×5 (~۰٫۳µs برای ۲KB) — memchr بهینه
            if (!\str_contains($value, '<') && !\str_contains($value, '&')
                && !\str_contains($value, '"') && !\str_contains($value, '>')
                && !\str_contains($value, "'")) {
                return $value;   // بدون کاراکتر خاص → escape لازم نیست
            }

            return \htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
        }

        if ($value === null) {
            return '';
        }
        if ($value instanceof SafeString) {
            return (string) $value;
        }
        if ($value instanceof ComponentAttributeBag) {
            return $value->toHtml();   // attributeها درون bag escape شده‌اند
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        if (\is_array($value) || (\is_object($value) && !$value instanceof \Stringable)) {
            return \htmlspecialchars(\json_encode($value, JSON_UNESCAPED_UNICODE) ?: '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
        }

        return \htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    }

    public static function attr(mixed $value): string
    {
        return self::html($value);   // ENT_QUOTES کافی است
    }

    /** JSON امن برای داخل <script> (JSON_HEX_* — DOC-043 §5). */
    public static function js(mixed $value): string
    {
        $json = \json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return $json === false ? 'null' : $json;
    }

    public static function url(mixed $value): string
    {
        return $value === null ? '' : \rawurlencode((string) $value);
    }

    public static function css(mixed $value): string
    {
        return $value === null ? '' : (\preg_replace('/[^a-zA-Z0-9\s\-_#,%.]/', '', (string) $value) ?? '');
    }
}

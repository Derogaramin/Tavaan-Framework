<?php

declare(strict_types=1);

namespace Tavaan\Engine\Security\Normalize;

/**
 * تَوان | Tavaan — Security
 * InputNormalizer — یکسان‌سازی ورودی کاربر (DOC-059 §2.7).
 *
 * - Unicode NFKC (در صورت وجود intl؛ وگرنه سبک)
 * - حذف کاراکترهای Bidi/Control/Zero-width خطرناک
 *   (🔒 ZWNJ U+200C و ZWJ U+200D برای فارسی/ایموجی **حفظ** می‌شوند)
 * - یکسان‌سازی ارقام عربی/فارسی → لاتین (برای validate)
 * - Trim + حذف null byte
 *
 * @api 1.0
 * @see docs/03-engines/security.md
 */
final class InputNormalizer
{
    /** کاراکترهای کنترلی/بایدی حذف‌شونده (Trojan Source / bidi attack) */
    private const STRIP_CHARS = [
        "\u{200B}",   // ZWSP
        "\u{200E}",   // LRM
        "\u{200F}",   // RLM
        "\u{202A}", "\u{202B}", "\u{202C}", "\u{202D}", "\u{202E}",   // bidi controls
        "\u{2066}", "\u{2067}", "\u{2068}", "\u{2069}",               // bidi isolates
        "\u{061C}",   // ARABIC LETTER MARK
        "\u{FEFF}",   // BOM/ZWNBSP
        "\u{00AD}",   // soft hyphen
        "\u{2060}",   // word joiner
        "\u{180E}",   // mongolian vowel separator
    ];

    /**
     * @param array<string, mixed> $config security.normalize
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function normalize(string $input): string
    {
        // null byte 🔒
        $input = \str_replace("\0", '', $input);

        if ((bool) ($this->config['unicode'] ?? true) && \class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($input, \Normalizer::FORM_KC);
            if (\is_string($normalized)) {
                $input = $normalized;
            }
        }

        if ((bool) ($this->config['strip_bidi'] ?? true)) {
            $input = \str_replace(self::STRIP_CHARS, '', $input);
            // سایر کاراکترهای کنترلی C0 (جز \n \t \r)
            $input = (string) \preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);
        }

        if ((bool) ($this->config['digits_to_latin'] ?? true)) {
            $input = \strtr($input, [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]);
            // ی/ک عربی → فارسی (یکسان‌سازی نمایشی، بدون تغییر معنا)
            $input = \strtr($input, ['ي' => 'ی', 'ك' => 'ک']);
        }

        return \trim($input);
    }

    /**
     * نرمال‌سازی بازگشتی روی آرایهٔ ورودی.
     *
     * @param array<array-key, mixed> $input
     *
     * @return array<array-key, mixed>
     */
    public function normalizeArray(array $input): array
    {
        $out = [];
        foreach ($input as $key => $value) {
            $out[$key] = match (true) {
                \is_string($value) => $this->normalize($value),
                \is_array($value) => $this->normalizeArray($value),
                default => $value,
            };
        }

        return $out;
    }
}

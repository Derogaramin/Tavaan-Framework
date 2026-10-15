<?php

declare(strict_types=1);

namespace Tavaan\Core\Error;

/**
 * تَوان | Tavaan — Core
 * LogMasker — پنهان‌سازی دادهٔ حساس قبل از نوشتن در لاگ/خروجی خطا.
 *
 * دو لایه محافظت:
 * 1. کلیدهای حساس (password, token, card_number, …) → `********`
 * 2. الگوهای متنی (شمارهٔ کارت، موبایل ایرانی، ایمیل، JWT، کلید base64) → mask جزئی
 *
 * @api 1.0
 * @see docs/03-engines/error.md
 */
final class LogMasker
{
    /** @var list<string> کلیدهای حساس پیش‌فرض (lowercase) */
    private const DEFAULT_KEYS = [
        'password', 'password_confirmation', 'pass', 'passwd', 'pwd',
        'token', 'access_token', 'refresh_token', 'id_token', 'api_key', 'apikey',
        'secret', 'client_secret', 'private_key', 'authorization', 'cookie',
        'card_number', 'card', 'pan', 'cvv', 'cvv2', 'expiry',
        'ssn', 'national_id', 'national_code', 'iban', 'account_number',
        'otp', 'verification_code', 'session_id',
    ];

    /** @var array<string, string> الگوهای regex برای mask درون رشته‌ها */
    private const DEFAULT_PATTERNS = [
        'card'      => '/\b(\d{4})\d{8,11}(\d{4})\b/',
        'mobile'    => '/\b(09\d{2})\d{3}(\d{4})\b/',
        'email'     => '/\b([A-Za-z0-9._%+-]{1,3})[A-Za-z0-9._%+-]*@([A-Za-z0-9.-]+\.[A-Za-z]{2,})\b/',
        'jwt'       => '/\beyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{5,}\b/',
        'base64key' => '/\b[A-Za-z0-9+\/]{40,}={0,2}\b/',
    ];

    private const REPLACEMENT = '********';

    private const MAX_DEPTH = 8;

    /** @var list<string> */
    private array $keys;

    /** @var array<string, string> */
    private array $patterns;

    private static ?self $default = null;

    /**
     * @param list<string>          $extraKeys     کلیدهای افزودنی
     * @param array<string, string> $extraPatterns الگوهای افزودنی
     */
    public function __construct(array $extraKeys = [], array $extraPatterns = [])
    {
        $this->keys = [...self::DEFAULT_KEYS, ...$extraKeys];
        $this->patterns = [...self::DEFAULT_PATTERNS, ...$extraPatterns];
    }

    /** نمونهٔ پیش‌فرض سراسری (برای فراخوانی‌های ایستا). */
    public static function default(): self
    {
        return self::$default ??= new self();
    }

    /**
     * Mask روی آرایهٔ context (بازگشتی، با سقف عمق).
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public static function mask(array $data, int $depth = 0): array
    {
        return self::default()->maskData($data, $depth);
    }

    /** Mask یک رشته بر اساس الگوها. */
    public static function maskString(string $value): string
    {
        return self::default()->maskStringValue($value);
    }

    /** افزودن کلیدهای حساس جدید. @param list<string> $keys */
    public function addKeys(array $keys): void
    {
        foreach ($keys as $key) {
            $this->keys[] = \strtolower($key);
        }
    }

    /** افزودن الگوی جدید. @param array<string, string> $patterns */
    public function addPatterns(array $patterns): void
    {
        foreach ($patterns as $name => $pattern) {
            $this->patterns[$name] = $pattern;
        }
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public function maskData(array $data, int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            return ['…truncated…'];
        }

        $out = [];
        foreach ($data as $k => $v) {
            $key = \is_string($k) ? \strtolower($k) : '';

            if ($key !== '' && $this->isSensitiveKey($key)) {
                $out[$k] = self::REPLACEMENT;
                continue;
            }
            if (\is_array($v)) {
                $out[$k] = $this->maskData($v, $depth + 1);
                continue;
            }
            if (\is_string($v)) {
                $out[$k] = $this->maskStringValue($v);
                continue;
            }
            if (\is_object($v)) {
                $out[$k] = '[object:' . $v::class . ']';
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    public function maskStringValue(string $value): string
    {
        foreach ($this->patterns as $pattern) {
            $value = \preg_replace_callback(
                $pattern,
                static fn (array $m): string => self::partial($m[0]),
                $value,
            ) ?? $value;
        }

        return $value;
    }

    private function isSensitiveKey(string $lowerKey): bool
    {
        if (\in_array($lowerKey, $this->keys, true)) {
            return true;
        }
        foreach ($this->keys as $key) {
            if (\str_ends_with($lowerKey, '_' . $key) || \str_ends_with($lowerKey, $key . '_')) {
                return true;
            }
        }

        return false;
    }

    /** mask جزئی: دو کاراکتر اول و آخر نمایان می‌ماند. */
    private static function partial(string $s): string
    {
        $len = \mb_strlen($s);
        if ($len <= 4) {
            return '****';
        }

        return \mb_substr($s, 0, 2) . \str_repeat('*', \max(2, $len - 4)) . \mb_substr($s, -2);
    }
}

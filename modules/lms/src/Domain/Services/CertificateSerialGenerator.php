<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CertificateSerialGenerator — serial یکتا و راستی‌آزمایی‌پذیر (DOC-095 §3.4).
 */

namespace Modules\Lms\Domain\Services;

/**
 * CertificateSerialGenerator — قالب: `<PREFIX>-<10 char base32>-<check>`.
 *
 * رقم کنترل = mod 31 از مجموع وزن‌دار کاراکترها (Crockford base32) —
 * اشتباه تایپیِ serial در verify عمومی زود رد می‌شود (بدون تماس با DB).
 *
 * @api 1.0
 */
final class CertificateSerialGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';   // Crockford (بدون I L O U)

    public function __construct(private readonly string $prefix = 'TVN-LMS')
    {
    }

    public function generate(): string
    {
        $body = '';
        $bytes = \random_bytes(10);
        for ($i = 0; $i < 10; $i++) {
            $body .= self::ALPHABET[\ord($bytes[$i]) % 32];
        }

        return $this->prefix . '-' . $body . '-' . self::ALPHABET[$this->checksum($body)];
    }

    /** ساختار serial معتبر است؟ (قالب + رقم کنترل) */
    public function isValidFormat(string $serial): bool
    {
        $pattern = '/^' . \preg_quote($this->prefix, '/') . '-([0-9A-HJKMNP-TV-Z]{10})-([0-9A-HJKMNP-TV-Z])$/';
        if (\preg_match($pattern, $serial, $m) !== 1) {
            return false;
        }

        return self::ALPHABET[$this->checksum($m[1])] === $m[2];
    }

    private function checksum(string $body): int
    {
        $sum = 0;
        $len = \strlen($body);
        for ($i = 0; $i < $len; $i++) {
            $sum += \strpos(self::ALPHABET, $body[$i]) * ($i + 1);
        }

        return $sum % 31;
    }
}

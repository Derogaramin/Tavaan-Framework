<?php

declare(strict_types=1);

namespace Tavaan\Core\Error;

/**
 * تَوان | Tavaan — Core
 * RefCodeGenerator — تولید کد ارجاع ۸ کاراکتری برای ردیابی خطا در پشتیبانی.
 *
 * الفبا: base32 بدون کاراکترهای مبهم (0/O/1/I) تا کاربر بتواند آن را بخواند.
 *
 * @api 1.0
 * @see docs/03-engines/error.md
 */
final class RefCodeGenerator
{
    /** @var string الفبای base32 بدون کاراکترهای مبهم */
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const LENGTH = 8;

    private function __construct()
    {
    }

    /**
     * تولید یک کد ارجاع تصادفی امن.
     */
    public static function generate(): string
    {
        $bytes = \random_bytes(self::LENGTH);
        $out = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= self::ALPHABET[\ord($bytes[$i]) % 32];
        }

        return $out;
    }
}

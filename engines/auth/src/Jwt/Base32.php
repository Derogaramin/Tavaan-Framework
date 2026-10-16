<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Jwt;

/**
 * تَوان | Tavaan — Auth
 * Base32 — کدگذاری RFC 4648 Base32 (برای کلید TOTP — otpauth://).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private function __construct()
    {
    }

    /** تولید کلید تصادفی امن (CSPRNG) — ۲۰ بایت → ۳۲ کاراکتر. */
    public static function randomSecret(int $bytes = 20): string
    {
        return self::encode(\random_bytes($bytes));
    }

    public static function encode(string $data): string
    {
        $binary = '';
        foreach (\str_split($data) as $char) {
            $binary .= \str_pad(\decbin(\ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (\str_split($binary, 5) as $chunk) {
            $chunk = \str_pad($chunk, 5, '0');
            $out .= self::ALPHABET[\bindec($chunk)];
        }

        // padding تا مضرب ۸
        while (\strlen($out) % 8 !== 0) {
            $out .= '=';
        }

        return $out;
    }

    public static function decode(string $secret): string
    {
        $secret = \strtoupper(\rtrim(\str_replace(' ', '', $secret), '='));

        $binary = '';
        $length = \strlen($secret);
        for ($i = 0; $i < $length; $i++) {
            $pos = \strpos(self::ALPHABET, $secret[$i]);
            if ($pos === false) {
                throw new \InvalidArgumentException(\sprintf('Invalid base32 character [%s].', $secret[$i]));
            }
            $binary .= \str_pad(\decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (\str_split(\substr($binary, 0, (int) (\strlen($binary) / 8) * 8), 8) as $byte) {
            $out .= \chr((int) \bindec($byte));
        }

        return $out;
    }
}

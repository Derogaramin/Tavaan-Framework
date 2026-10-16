<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\TwoFactor;

use Tavaan\Engine\Auth\Jwt\Base32;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Auth
 * Totp — رمز یک‌بارمصرف مبتنی بر زمان (RFC 6238 — DOC-056 §7.1).
 *
 * 🔒 verify با hash_equals (constant-time) و پنجرهٔ ±window.
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class Totp
{
    public function __construct(
        private readonly int $period = 30,
        private readonly int $digits = 6,
        private readonly int $window = 1,
    ) {
    }

    /** تولید کد برای یک secret (base32) در زمان مشخص. */
    public function generate(string $secret, ?int $timestamp = null): string
    {
        return $this->hotp(Base32::decode($secret), $timestamp ?? Clock::timestamp());
    }

    /** HOTP (RFC 4226) — پایهٔ TOTP. */
    private function hotp(string $key, int $timestamp): string
    {
        $counter = \intdiv($timestamp, $this->period);
        $binCounter = \pack('N*', 0, $counter);

        $hash = \hash_hmac('sha1', $binCounter, $key, true);
        $offset = \ord($hash[\strlen($hash) - 1]) & 0x0F;

        $code = (
            ((\ord($hash[$offset]) & 0x7F) << 24)
            | ((\ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((\ord($hash[$offset + 2]) & 0xFF) << 8)
            | (\ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** $this->digits);

        return \str_pad((string) $code, $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * اعتبارسنجی با پنجرهٔ زمانی ±window.
     *
     * 🔒 یک کد فقط یک‌بار مصرف شود (replay) — توسط لایهٔ بالاتر (cache).
     */
    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $now = $timestamp ?? Clock::timestamp();

        if (\preg_match('/^\d{' . $this->digits . '}$/', $code) !== 1) {
            return false;
        }

        for ($i = -$this->window; $i <= $this->window; $i++) {
            $expected = $this->hotp(Base32::decode($secret), $now + $i * $this->period);
            if (\hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /** URI برای اپ‌های احراز (Google Authenticator و …). */
    public function provisioningUri(string $secret, string $accountName, string $issuer = 'Tavaan'): string
    {
        return 'otpauth://totp/' . \rawurlencode($issuer) . ':' . \rawurlencode($accountName)
            . '?secret=' . \rawurlencode($secret)
            . '&issuer=' . \rawurlencode($issuer)
            . '&algorithm=SHA1'
            . '&digits=' . $this->digits
            . '&period=' . $this->period;
    }

    public static function generateSecret(int $bytes = 20): string
    {
        return Base32::randomSecret($bytes);
    }
}

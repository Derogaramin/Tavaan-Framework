<?php

declare(strict_types=1);

namespace Tavaan\Support;

/**
 * تَوان | Tavaan — Support
 * Ulid — شناسهٔ یکتای مرتب‌زمانی (ADR-0006: کلید اصلی = ULID).
 *
 * ساختار ۱۲۸ بیتی: ۴۸ بیت timestamp (ms) + ۸۰ بیت تصادفی CSPRNG.
 * کدگذاری Crockford Base32 → ۲۶ کاراکتر؛ مرتب‌سازی لغوی = مرتب‌سازی زمانی.
 *
 * @api 1.0
 * @see docs/adr/ADR-0006-primary-key-ulid.md
 */
final readonly class Ulid implements \Stringable
{
    /** الفبای Crockford Base32 (بدون I/L/O/U) */
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** @param array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int, 8: int, 9: int, 10: int, 11: int, 12: int, 13: int, 14: int, 15: int} $bytes */
    private function __construct(private array $bytes)
    {
    }

    public static function generate(?\DateTimeImmutable $at = null): self
    {
        $ms = $at === null
            ? (int) \floor(\microtime(true) * 1000)
            : (int) \floor((float) $at->format('U.v') * 1000);

        $random = \random_bytes(10);

        $bytes = [
            ($ms >> 40) & 0xFF, ($ms >> 32) & 0xFF, ($ms >> 24) & 0xFF, ($ms >> 16) & 0xFF,
            ($ms >> 8) & 0xFF, $ms & 0xFF,
            \ord($random[0]), \ord($random[1]), \ord($random[2]), \ord($random[3]), \ord($random[4]),
            \ord($random[5]), \ord($random[6]), \ord($random[7]), \ord($random[8]), \ord($random[9]),
        ];

        return new self($bytes);
    }

    /**
     * ساخت از رشتهٔ ۲۶ کاراکتری.
     *
     * @throws \InvalidArgumentException اگر قالب نامعتبر باشد
     */
    public static function fromString(string $ulid): self
    {
        $ulid = \strtoupper(\str_replace(['O', 'I', 'L'], ['0', '1', '1'], \trim($ulid)));
        if (\preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid) !== 1) {
            throw new \InvalidArgumentException('Invalid ULID string.');
        }

        // decode ۲۶ کاراکتر base32 → ۱۳۰ بیت → ۱۲۸ بیت (۲ بیت اول باید صفر باشند)
        $bits = '';
        for ($i = 0; $i < 26; $i++) {
            $bits .= \str_pad(\decbin((int) \strpos(self::ALPHABET, $ulid[$i])), 5, '0', STR_PAD_LEFT);
        }
        if ($bits[0] !== '0' || $bits[1] !== '0') {
            throw new \InvalidArgumentException('Invalid ULID string (overflow).');
        }

        $bytes = [];
        for ($i = 2; $i < 130; $i += 8) {
            $bytes[] = (int) \bindec(\substr($bits, $i, 8));
        }

        /** @var array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int, 8: int, 9: int, 10: int, 11: int, 12: int, 13: int, 14: int, 15: int} $bytes */
        return new self($bytes);
    }

    public function toString(): string
    {
        $bits = '';
        foreach ($this->bytes as $byte) {
            $bits .= \str_pad(\decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $bits = '00' . $bits;   // ۱۳۰ بیت

        $out = '';
        for ($i = 0; $i < 26; $i++) {
            $out .= self::ALPHABET[\bindec(\substr($bits, $i * 5, 5))];
        }

        return $out;
    }

    /** زمان ساخت (millisecond epoch). */
    public function timestampMs(): int
    {
        $ms = 0;
        for ($i = 0; $i < 6; $i++) {
            $ms = ($ms << 8) | $this->bytes[$i];
        }

        return $ms;
    }

    public function toDateTime(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . \intdiv($this->timestampMs(), 1000)))
            ->setTime(0, 0, 0, ($this->timestampMs() % 1000) * 1000);
    }

    /** نمای باینری ۱۶ بایتی. */
    public function toBytes(): string
    {
        $out = '';
        foreach ($this->bytes as $byte) {
            $out .= \chr($byte);
        }

        return $out;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Crypt;

use Tavaan\Engine\Crypt\Exceptions\KeyMissingException;

/**
 * تَوان | Tavaan — Crypt
 * KeyRing — حلقهٔ کلیدها با versioning (پشتیبانی از Rotation — DOC-054 §5).
 *
 * قالب APP_KEY: `base64:<44char>[,base64:<44char>…]` — آخرین نسخه = جاری.
 * رمزنگاری همیشه با جدیدترین کلید؛ رمزگشایی با کلید همان نسخهٔ payload.
 *
 * @api 1.0
 * @see docs/03-engines/crypt.md
 */
final class KeyRing
{
    /** @var array<int, string> version → raw 32-byte key */
    private array $keys = [];

    private int $current = 0;

    /**
     * @param string $configKey رشتهٔ کلید(ها) از config (ممکن است خالی باشد)
     */
    public function __construct(string $configKey = '')
    {
        if ($configKey === '') {
            return;
        }

        foreach (\explode(',', $configKey) as $i => $version) {
            $version = \trim($version);
            if ($version === '') {
                continue;
            }
            $this->keys[$i + 1] = $this->decode($version);
        }

        $last = \array_key_last($this->keys);
        $this->current = $last ?? 0;
    }

    public function hasKeys(): bool
    {
        return $this->keys !== [];
    }

    public function currentVersion(): int
    {
        if ($this->current === 0) {
            throw KeyMissingException::notConfigured();
        }

        return $this->current;
    }

    /** کلید خام یک نسخه؛ null اگر موجود نباشد. */
    public function key(int $version): ?string
    {
        return $this->keys[$version] ?? null;
    }

    /** کلید جاری (خام). */
    public function currentKey(): string
    {
        $key = $this->key($this->currentVersion());
        if ($key === null) {
            throw KeyMissingException::forVersion($this->current);
        }

        return $key;
    }

    /** رمزگشایی `base64:...` یا hex یا raw به ۳۲ بایت. */
    private function decode(string $value): string
    {
        if (\str_starts_with($value, 'base64:')) {
            $raw = \base64_decode(\substr($value, 7), true);
            if ($raw === false || \strlen($raw) !== 32) {
                throw KeyMissingException::notConfigured();
            }

            return $raw;
        }

        // کلید غیر base64 → derive با HKDF به ۳۲ بایت (ایمن‌سازی حداقلی)
        return Crypt::hkdf($value, 'tavaan.app_key', 32);
    }

    /** تولید APP_KEY جدید (`tvn key:generate`). */
    public static function generate(): string
    {
        return 'base64:' . \base64_encode(\random_bytes(32));
    }
}

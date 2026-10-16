<?php

declare(strict_types=1);

namespace Tavaan\Engine\Hash;

use Tavaan\Core\Contracts\Encryption\HasherInterface;

/**
 * تَوان | Tavaan — Hash
 * BcryptHasher — درهم‌ساز bcrypt (fallback وقتی Argon2id موجود نیست — DOC-055).
 *
 * 🔒 md5/sha1/sha256 برای رمز عبور هرگز (DOC-055 §6).
 *
 * @api 1.0
 * @see docs/03-engines/hash.md
 */
final class BcryptHasher implements HasherInterface
{
    public function __construct(private readonly int $cost = 12)
    {
        if ($this->cost < 10) {
            throw new \InvalidArgumentException('bcrypt cost must be >= 10 (security policy).');
        }
    }

    public function make(#[\SensitiveParameter] string $value): string
    {
        return \password_hash($value, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function check(#[\SensitiveParameter] string $value, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        return \password_verify($value, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return \password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    /** @return array<string, mixed> */
    public function info(string $hash): array
    {
        return \password_get_info($hash);
    }
}

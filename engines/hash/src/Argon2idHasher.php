<?php

declare(strict_types=1);

namespace Tavaan\Engine\Hash;

use Tavaan\Core\Contracts\Encryption\HasherInterface;

/**
 * تَوان | Tavaan — Hash
 * Argon2idHasher — درهم‌ساز پیش‌فرض با Argon2id (DOC-055 §3).
 *
 * 🔒 threads=1 (جلوگیری از حملات side-channel موازی).
 * 🔒 در نبود PASSWORD_ARGON2ID → bcrypt fallback (بدون crash).
 * 🔒 check با password_verify (constant-time داخلی).
 *
 * @api 1.0
 * @see docs/03-engines/hash.md
 */
final class Argon2idHasher implements HasherInterface
{
    public function __construct(
        private readonly int $memoryCost = 65536,
        private readonly int $timeCost = 4,
        private readonly int $threads = 1,
        private readonly int $bcryptCost = 12,
    ) {
    }

    public function make(#[\SensitiveParameter] string $value): string
    {
        if (!\defined('PASSWORD_ARGON2ID')) {
            return $this->bcryptFallback($value);
        }

        return \password_hash($value, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memoryCost,
            'time_cost'   => $this->timeCost,
            'threads'     => $this->threads,
        ]);
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
        if (!\defined('PASSWORD_ARGON2ID')) {
            return \password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $this->bcryptCost]);
        }

        return \password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memoryCost,
            'time_cost'   => $this->timeCost,
            'threads'     => $this->threads,
        ]);
    }

    /** @return array<string, mixed> */
    public function info(string $hash): array
    {
        return \password_get_info($hash);
    }

    private function bcryptFallback(#[\SensitiveParameter] string $value): string
    {
        return \password_hash($value, PASSWORD_BCRYPT, ['cost' => $this->bcryptCost]);
    }
}

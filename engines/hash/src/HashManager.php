<?php

declare(strict_types=1);

namespace Tavaan\Engine\Hash;

use Tavaan\Core\Contracts\Config\RepositoryInterface;
use Tavaan\Core\Contracts\Encryption\HasherInterface;

/**
 * تَوان | Tavaan — Hash
 * HashManager — مدیر درایورهای هش (argon2id|bcrypt — DOC-055).
 *
 * @api 1.0
 * @see docs/03-engines/hash.md
 */
final class HashManager implements HasherInterface
{
    private ?HasherInterface $resolved = null;

    public function __construct(private readonly ?RepositoryInterface $config = null)
    {
    }

    public function driver(?string $name = null): HasherInterface
    {
        if ($this->resolved !== null && $name === null) {
            return $this->resolved;
        }

        $name ??= (string) ($this->config?->get('hash.driver', 'argon2id') ?? 'argon2id');

        $hasher = match ($name) {
            'argon2id' => new Argon2idHasher(
                (int) ($this->config?->get('hash.argon2id.memory_cost', 65536) ?? 65536),
                (int) ($this->config?->get('hash.argon2id.time_cost', 4) ?? 4),
                (int) ($this->config?->get('hash.argon2id.threads', 1) ?? 1),
                (int) ($this->config?->get('hash.bcrypt.cost', 12) ?? 12),
            ),
            'bcrypt' => new BcryptHasher(
                (int) ($this->config?->get('hash.bcrypt.cost', 12) ?? 12),
            ),
            default => throw new \InvalidArgumentException(\sprintf('Unknown hash driver [%s].', $name)),
        };

        if ($name === null || $this->resolved === null) {
            $this->resolved = $hasher;
        }

        return $hasher;
    }

    public function make(#[\SensitiveParameter] string $value): string
    {
        return $this->driver()->make($value);
    }

    public function check(#[\SensitiveParameter] string $value, string $hash): bool
    {
        return $this->driver()->check($value, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return $this->driver()->needsRehash($hash);
    }

    /** @return array<string, mixed> */
    public function info(string $hash): array
    {
        return $this->driver()->info($hash);
    }
}

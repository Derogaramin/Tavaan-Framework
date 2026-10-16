<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Auth;

/**
 * تَوان | Tavaan — Contracts
 * UserProviderInterface — قرارداد منبع کاربران (DOC-014 §2.6).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
interface UserProviderInterface
{
    public function retrieveById(mixed $identifier): ?Authenticatable;

    public function retrieveByToken(mixed $identifier, string $token): ?Authenticatable;

    public function updateRememberToken(Authenticatable $user, string $token): void;

    /**
     * @param array<string, mixed> $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable;

    /**
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool;

    /**
     * Rehash خودکار در صورت قدیمی بودن پارامترها.
     *
     * @param array<string, mixed> $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials): void;
}

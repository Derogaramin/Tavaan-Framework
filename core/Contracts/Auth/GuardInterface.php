<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Auth;

/**
 * تَوان | Tavaan — Contracts
 * GuardInterface — قرارداد نگهبان احراز هویت (DOC-014 §2.6).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
interface GuardInterface
{
    public function check(): bool;

    public function guest(): bool;

    public function user(): ?Authenticatable;

    public function id(): int|string|null;

    /**
     * فقط بررسی اعتبارنامه (بدون ورود).
     *
     * @param array<string, mixed> $credentials
     */
    public function validate(array $credentials): bool;

    public function setUser(Authenticatable $user): void;
}

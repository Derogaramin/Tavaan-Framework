<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Auth;

/**
 * تَوان | Tavaan — Contracts
 * Authenticatable — قرارداد موجودیت قابل احراز (DOC-014 §2.6).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
interface Authenticatable
{
    public function getAuthIdentifier(): int|string;

    public function getAuthIdentifierName(): string;

    /** رمز درهم‌سازی‌شده (هرگز خام). */
    public function getAuthPassword(): string;

    public function getRememberToken(): ?string;

    public function setRememberToken(string $value): void;

    public function getRememberTokenName(): string;
}

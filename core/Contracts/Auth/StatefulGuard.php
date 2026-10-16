<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Auth;

/**
 * تَوان | Tavaan — Contracts
 * StatefulGuard — قرارداد نگهبان دارای نشست (ورود/خروج — DOC-014 §2.6).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
interface StatefulGuard extends GuardInterface
{
    /**
     * تلاش برای ورود با اعتبارنامه.
     *
     * @param array<string, mixed> $credentials
     */
    public function attempt(array $credentials, bool $remember = false): bool;

    /**
     * ورود یک‌باره بدون دست‌زدن به Session.
     *
     * @param array<string, mixed> $credentials
     */
    public function once(array $credentials): bool;

    public function login(Authenticatable $user, bool $remember = false): void;

    public function loginUsingId(mixed $id, bool $remember = false): ?Authenticatable;

    public function logout(): void;
}

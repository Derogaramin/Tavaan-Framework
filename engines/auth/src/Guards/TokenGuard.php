<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Guards;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Auth\GuardInterface;
use Tavaan\Core\Contracts\Auth\UserProviderInterface;
use Tavaan\Engine\Auth\Exceptions\TokenExpiredException;
use Tavaan\Engine\Auth\Exceptions\TokenInvalidException;
use Tavaan\Engine\Auth\Jwt\JwtEncoder;

/**
 * تَوان | Tavaan — Auth
 * TokenGuard — نگهبان API با Bearer JWT (DOC-056 §3/§6).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class TokenGuard implements GuardInterface
{
    private ?Authenticatable $user = null;

    /** claims آخرین توکن معتبر */
    private ?array $claims = null;

    /** @var \Closure(): ?string resolver توکن (header bearer) */
    private $tokenResolver;

    public function __construct(
        private readonly string $name,
        private readonly UserProviderInterface $provider,
        private readonly JwtEncoder $jwt,
        ?\Closure $tokenResolver = null,
    ) {
        $this->tokenResolver = $tokenResolver ?? static fn (): ?string => null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = ($this->tokenResolver)();
        if (!\is_string($token) || $token === '') {
            return null;
        }

        return $this->authenticate($token);
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * احراز با توکن صریح.
     *
     * @throws TokenInvalidException|TokenExpiredException
     */
    public function authenticate(string $token): ?Authenticatable
    {
        $claims = $this->jwt->decode($token);

        $subject = $claims['sub'] ?? null;
        if ($subject === null || (!\is_string($subject) && !\is_int($subject))) {
            throw new TokenInvalidException('auth.token.missing_subject');
        }

        $user = $this->provider->retrieveById($subject);
        if ($user === null) {
            throw new TokenInvalidException('auth.token.subject_not_found');
        }

        $this->claims = $claims;
        $this->user = $user;

        return $user;
    }

    /** claims آخرین توکن معتبر (scopes/jti/…). */
    public function claims(): ?array
    {
        return $this->claims;
    }

    public function validate(array $credentials): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return $user !== null && $this->provider->validateCredentials($user, $credentials);
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    /** پاک‌کردن کاربر احراز‌شدهٔ کش‌شده — ابطال توکن/تست. */
    public function forgetUser(): void
    {
        $this->user = null;
    }

    public function name(): string
    {
        return $this->name;
    }
}

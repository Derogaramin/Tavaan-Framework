<?php

declare(strict_types=1);

namespace Tavaan\Engine\FeatureFlag\Tests\Fixtures;

use Tavaan\Core\Contracts\Tenancy\TenantInterface;

/**
 * Fixtureهای تست FeatureFlag.
 *
 * @api internal
 */

/** کاربر ساختگی با نقش/مجوز. */
final class FUser
{
    /**
     * @param list<string> $roles
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly int $id,
        public readonly array $roles = [],
        public readonly array $permissions = [],
    ) {
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    /** @param list<string> $roles */
    public function hasAnyRole(array $roles): bool
    {
        return \array_intersect($roles, $this->roles) !== [];
    }

    /** @param list<string> $permissions */
    public function canAny(array $permissions): bool
    {
        return \array_intersect($permissions, $this->permissions) !== [];
    }

    public function can(string $permission): bool
    {
        return \in_array($permission, $this->permissions, true);
    }
}

/** tenant ساختگی (بدون DB) برای قاعدهٔ tenant. */
final class FTenant implements TenantInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $identifier,
    ) {
    }

    public function id(): int|string|null
    {
        return $this->id;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function name(): string
    {
        return $this->identifier;
    }

    public function domain(): ?string
    {
        return null;
    }

    public function plan(): string
    {
        return 'free';
    }

    public function isActive(): bool
    {
        return true;
    }

    public function settings(): array
    {
        return [];
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function quota(string $resource): ?array
    {
        return null;
    }

    public function databaseName(): string
    {
        return 'tenant_' . $this->identifier;
    }
}

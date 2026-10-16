<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Tests\Fixtures;

/**
 * فیکسچرهای تست موتور Workflow (DOC-071 §9).
 */

/** کاربر ساده با مجوزهای صریح (can) — برای guard/authorize. */
final class WfUser
{
    /** @param list<string> $permissions */
    public function __construct(
        private readonly int $id,
        private readonly array $permissions = [],
    ) {
    }

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function can(string $ability): bool
    {
        return \in_array($ability, $this->permissions, true);
    }
}

/** گارد auth تقلبی برای تست (user() قابل تعویض). */
final class WfAuth
{
    public function __construct(private ?WfUser $user = null)
    {
    }

    public function user(): ?WfUser
    {
        return $this->user;
    }

    public function setUser(?WfUser $user): void
    {
        $this->user = $user;
    }
}

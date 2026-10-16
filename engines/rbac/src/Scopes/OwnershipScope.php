<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac\Scopes;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Database\QueryBuilderInterface;
use Tavaan\Core\Contracts\ORM\ModelInterface;
use Tavaan\Core\Contracts\ORM\ScopeInterface;

/**
 * تَوان | Tavaan — RBAC
 * OwnershipScope — Row-Level Scope مالکیت (DOC-057 §6.3 — AC-RBAC-13: نشت = ۰).
 *
 * ادمین (view-all) → همهٔ رکوردها؛ غیرادمین → فقط رکوردهای خودش.
 *
 * @api 1.0
 * @see docs/03-engines/rbac.md
 */
final class OwnershipScope implements ScopeInterface
{
    /** @var \Closure(): ?Authenticatable */
    private readonly \Closure $userResolver;

    /**
     * @param null|\Closure(): ?Authenticatable $userResolver
     */
    public function __construct(
        private readonly string $ownerColumn = 'user_id',
        private readonly string $viewAllAbility = '*.view-all',
        ?\Closure $userResolver = null,
    ) {
        $this->userResolver = $userResolver ?? static function (): ?Authenticatable {
            if (!\class_exists(\Tavaan\Core\Facade\Facades\Auth::class)) {
                return null;
            }

            try {
                $user = \Tavaan\Core\Facade\Facades\Auth::user();

                return $user instanceof Authenticatable ? $user : null;
            } catch (\Throwable) {
                return null;
            }
        };
    }

    public function apply(QueryBuilderInterface $query, ModelInterface $model): void
    {
        $user = ($this->userResolver)();

        if ($user === null) {
            // 🔒 Deny by default — کاربر ناشناس هیچ رکوردی نبیند
            $query->whereRaw('0 = 1');

            return;
        }

        if ($this->canViewAll($user)) {
            return;   // ادمین: همه
        }

        $column = $this->ownerColumn;
        if (\method_exists($model, 'getOwnerColumn')) {
            $column = (string) $model->getOwnerColumn();
        }

        $query->where($column, $user->getAuthIdentifier());
    }

    private function canViewAll(Authenticatable $user): bool
    {
        if (\method_exists($user, 'can')) {
            return (bool) $user->can($this->viewAllAbility);
        }

        if (\method_exists($user, 'hasPermission')) {
            return (bool) $user->hasPermission($this->viewAllAbility);
        }

        return false;
    }
}

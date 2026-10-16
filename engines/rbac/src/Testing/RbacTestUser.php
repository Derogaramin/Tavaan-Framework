<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac\Testing;

use Tavaan\Engine\Auth\GenericUser;
use Tavaan\Engine\Rbac\Concerns\HasRoles;

/**
 * تَوان | Tavaan — RBAC
 * RbacTestUser — کاربر تست با trait HasRoles (DOC-014 §4 — Fake).
 *
 * @api internal
 * @see docs/03-engines/rbac.md
 */
final class RbacTestUser extends GenericUser
{
    use HasRoles;
}

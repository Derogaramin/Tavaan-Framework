<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Authorization\GateInterface;
use Tavaan\Core\Contracts\Authorization\ResponseInterface;
use Tavaan\Core\Exceptions\Authorization\AuthorizationException;

/**
 * تَوان | Tavaan — RBAC
 * Gate — دروازهٔ کنترل دسترسی ترکیبی RBAC+ABAC (DOC-057 §3/§6.2).
 *
 * جریان check:
 * ```
 * 1. before callbacks → true/false قطعی
 * 2. Policy برای مدل (اگر آرگومان مدل/کلاس بود)
 * 3. ability تعریف‌شده (define)
 * 4. hasPermission(ability) از نقش‌ها (با سلسله‌مراتب/wildcard/delegation)
 * 5. after callbacks
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/rbac.md
 */
final class Gate implements GateInterface
{
    /** @var array<string, callable|class-string> */
    private array $abilities = [];

    /** @var array<class-string, class-string|object> */
    private array $policies = [];

    /** @var list<callable> */
    private array $beforeCallbacks = [];

    /** @var list<callable> */
    private array $afterCallbacks = [];

    /** @var array<class-string, object> کش نمونه‌های policy */
    private array $policyInstances = [];

    private ?Authenticatable $forUser = null;

    /** نگاشت ability CRUD → متد Policy (DOC-057 §4) */
    private const POLICY_METHODS = [
        'view' => 'view',
        'viewAny' => 'viewAny',
        'create' => 'create',
        'update' => 'update',
        'delete' => 'delete',
        'restore' => 'restore',
        'forceDelete' => 'forceDelete',
    ];

    public function __construct(
        private readonly PermissionResolver $permissions,
        /** @var \Closure(): ?Authenticatable */
        private readonly \Closure $userResolver,
        private readonly array $config = [],
    ) {
    }

    // ─── تعریف ────────────────────────────────────────────────────────────

    public function define(string $ability, callable|string $callback): static
    {
        $this->abilities[$ability] = $callback;

        return $this;
    }

    public function policy(string $model, string|object $policy): static
    {
        $this->policies[$model] = $policy;

        return $this;
    }

    public function before(callable $callback): static
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    public function after(callable $callback): static
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    public function forUser(mixed $user): static
    {
        $clone = clone $this;
        $clone->forUser = $user instanceof Authenticatable ? $user : null;

        return $clone;
    }

    /** @return array<string, callable|class-string> */
    public function abilities(): array
    {
        return $this->abilities;
    }

    // ─── بررسی ────────────────────────────────────────────────────────────

    public function allows(string $ability, mixed ...$arguments): bool
    {
        return $this->checkAbility($ability, $arguments) === true;
    }

    public function denies(string $ability, mixed ...$arguments): bool
    {
        return !$this->allows($ability, ...$arguments);
    }

    public function check(iterable|string $abilities, mixed ...$arguments): bool
    {
        $abilities = \is_string($abilities) ? [$abilities] : $abilities;

        foreach ($abilities as $ability) {
            if (!$this->allows((string) $ability, ...$arguments)) {
                return false;
            }
        }

        return true;
    }

    public function any(iterable|string $abilities, mixed ...$arguments): bool
    {
        $abilities = \is_string($abilities) ? [$abilities] : $abilities;

        foreach ($abilities as $ability) {
            if ($this->allows((string) $ability, ...$arguments)) {
                return true;
            }
        }

        return false;
    }

    public function authorize(string $ability, mixed ...$arguments): ResponseInterface
    {
        if ($this->allows($ability, ...$arguments)) {
            return GateResponse::allow();
        }

        throw new AuthorizationException(
            messageId: 'authorization.denied',
            context: ['ability' => $ability],
            hint: 'مجوز لازم برای این عملیات وجود ندارد.',
        );
    }

    public function getPolicyFor(object|string $class): ?object
    {
        $className = \is_object($class) ? $class::class : $class;

        // نگاشت مستقیم یا از طریق والد/اینترفیس
        $policy = $this->policies[$className] ?? null;
        if ($policy === null && \is_string($class)) {
            foreach ($this->policies as $model => $candidate) {
                if (\is_subclass_of($className, $model) || $className === $model) {
                    $policy = $candidate;
                    break;
                }
            }
        }

        if ($policy === null) {
            return null;
        }

        if (\is_object($policy)) {
            return $policy;
        }

        return $this->policyInstances[$policy] ??= new $policy();
    }

    // ─── موتور اصلی ───────────────────────────────────────────────────────

    /** @param list<mixed> $arguments */
    private function checkAbility(string $ability, array $arguments): bool
    {
        $user = $this->resolveUser();

        // 1) before
        foreach ($this->beforeCallbacks as $before) {
            $result = $before($user, $ability, $arguments);
            if ($result === true || $result === false) {
                return $this->runAfter($user, $ability, $arguments, $result);
            }
        }

        if ($user === null) {
            return $this->runAfter($user, $ability, $arguments, false);
        }

        // 1.5) super-admin bypass (🔒 همیشه audit شود — توسط listener رویداد)
        if ($this->isSuperAdmin($user)) {
            return $this->runAfter($user, $ability, $arguments, true);
        }

        // 2) Policy
        $policyResult = $this->callPolicy($user, $ability, $arguments);
        if ($policyResult !== null) {
            return $this->runAfter($user, $ability, $arguments, (bool) $policyResult);
        }

        // 3) ability تعریف‌شده
        if (isset($this->abilities[$ability])) {
            $callback = $this->abilities[$ability];
            if (\is_string($callback) && \class_exists($callback)) {
                $callback = $this->policyInstances[$callback] ??= new $callback();
            }
            $result = \is_callable($callback) ? $callback($user, ...$arguments) : false;

            return $this->runAfter($user, $ability, $arguments, (bool) $result);
        }

        // 4) fallback: hasPermission از نقش‌ها
        $granted = $this->permissions->effectivePermissions($user->getAuthIdentifier());

        return $this->runAfter($user, $ability, $arguments, $this->permissions->matches($ability, $granted));
    }

    /** @param list<mixed> $arguments */
    private function runAfter(?Authenticatable $user, string $ability, array $arguments, bool $result): bool
    {
        foreach ($this->afterCallbacks as $after) {
            $afterResult = $after($user, $ability, $result, $arguments);
            if ($afterResult === true || $afterResult === false) {
                $result = $afterResult;
            }
        }

        return $result;
    }

    /**
     * @param list<mixed> $arguments
     */
    private function callPolicy(?Authenticatable $user, string $ability, array $arguments): ?bool
    {
        if ($user === null) {
            return null;
        }

        $model = null;
        foreach ($arguments as $argument) {
            if (\is_object($argument)) {
                $model = $argument;
                break;
            }
        }

        if ($model === null) {
            // viewAny/create بدون نمونه → policy با class-string ثبت‌شده؟
            return null;
        }

        $policy = $this->getPolicyFor($model);
        if ($policy === null) {
            return null;
        }

        $method = self::POLICY_METHODS[$ability] ?? $ability;
        if (!\method_exists($policy, $method)) {
            return null;
        }

        return (bool) $policy->{$method}($user, $model, ...\array_slice($arguments, 1));
    }

    private function resolveUser(): ?Authenticatable
    {
        return $this->forUser ?? ($this->userResolver)();
    }

    private function isSuperAdmin(Authenticatable $user): bool
    {
        if (!(bool) ($this->config['super_admin']['bypasses_gate'] ?? true)) {
            return false;
        }

        $role = (string) ($this->config['super_admin']['role'] ?? 'super-admin');
        $roles = $this->userRoles($user);

        return \in_array($role, $roles, true);
    }

    /** @return list<string> کلید نقش‌های مستقیم کاربر */
    private function userRoles(Authenticatable $user): array
    {
        if (\method_exists($user, 'roleKeys')) {
            $keys = $user->roleKeys();
            if (\is_array($keys)) {
                return \array_map('strval', $keys);
            }
        }

        return [];
    }

    public function resolver(): PermissionResolver
    {
        return $this->permissions;
    }
}

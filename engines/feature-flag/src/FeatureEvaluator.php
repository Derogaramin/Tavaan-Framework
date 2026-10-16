<?php

declare(strict_types=1);

namespace Tavaan\Engine\FeatureFlag;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Tenancy\TenantInterface;

/**
 * تَوان | Tavaan — FeatureFlag
 * FeatureEvaluator — ارزیابی قوانین یک feature (DOC-075 §3).
 *
 * قواعد: role / permission / user_id / tenant / percentage / env.
 * 🔒 percentage پایدار است (crc32(seed|feature) — همان کاربر همیشه
 * در همان bucket می‌ماند).
 *
 * @api 1.0
 * @see docs/03-engines/feature-flag.md
 */
final class FeatureEvaluator
{
    public function __construct(
        private readonly ?CacheRepository $cache = null,
        private readonly ?string $environment = null,
        private readonly ?string $requestIp = null,
    ) {
    }

    /**
     * @param array<string, mixed> $definition
     */
    public function evaluate(array $definition, string $feature, ?object $user = null): bool
    {
        // Kill Switch سراسری
        if ($this->cache !== null && $this->cache->get($this->killKey($feature)) !== null) {
            return false;
        }

        // کلید اصلی خاموش → سخت‌خاموش
        if (\array_key_exists('enabled', $definition) && !(bool) $definition['enabled']) {
            return false;
        }

        foreach ((array) ($definition['rules'] ?? []) as $rule) {
            if (\is_array($rule) && $this->matchRule($rule, $feature, $user)) {
                return true;
            }
        }

        return (bool) ($definition['default'] ?? false);
    }

    /**
     * @param array<string, mixed> $rule
     */
    public function matchRule(array $rule, string $feature, ?object $user = null): bool
    {
        $type = (string) ($rule['type'] ?? '');
        $value = $rule['value'] ?? null;

        return match ($type) {
            'role' => $user !== null && $this->userHasAnyRole($user, (array) $value),
            'permission' => $user !== null && $this->userCanAny($user, (array) $value),
            'user_id' => $user !== null && \in_array($this->userId($user), \array_map('strval', (array) $value), true),
            'tenant' => \in_array($this->tenantIdentifier(), \array_map('strval', (array) $value), true),
            'percentage' => $this->inPercentage($feature, $user, (int) $value),
            'env' => \in_array((string) $this->environment, \array_map('strval', (array) $value), true),
            'ip' => $this->requestIp !== null && \in_array($this->requestIp, \array_map('strval', (array) $value), true),
            default => false,
        };
    }

    /**
     * 🔒 درصد پایدار بر اساس seed کاربر (نه random در هر درخواست).
     * seed = user id → request ip → 'anon'. نام feature هم در hash می‌آید
     * تا bucketهای دو flag مستقل بمانند (همان کاربر/همان flag = همان نتیجه).
     */
    public function inPercentage(string $feature, ?object $user, int $percentage): bool
    {
        if ($percentage <= 0) {
            return false;
        }
        if ($percentage >= 100) {
            return true;
        }

        $seed = $this->seed($user);
        $bucket = \crc32($seed . '|' . $feature) % 100;

        return $bucket < $percentage;
    }

    /** seed پایدار کاربر برای percentage/experiment. */
    public function seed(?object $user): string
    {
        if ($user !== null) {
            $id = $this->userId($user);
            if ($id !== '') {
                return 'u:' . $id;
            }
        }
        if ($this->requestIp !== null && $this->requestIp !== '') {
            return 'ip:' . $this->requestIp;
        }

        return 'anon';
    }

    public function killKey(string $feature): string
    {
        return 'feature:kill:' . $feature;
    }

    private function userId(?object $user): string
    {
        if ($user === null) {
            return '';
        }
        if (\method_exists($user, 'getAuthIdentifier')) {
            return (string) $user->getAuthIdentifier();
        }
        if (isset($user->id)) {
            return (string) $user->id;
        }

        return '';
    }

    private function userHasAnyRole(object $user, array $roles): bool
    {
        if (\method_exists($user, 'hasAnyRole')) {
            return (bool) $user->hasAnyRole($roles);
        }
        foreach ($roles as $role) {
            if (\method_exists($user, 'hasRole') && $user->hasRole((string) $role)) {
                return true;
            }
        }

        return false;
    }

    private function userCanAny(object $user, array $permissions): bool
    {
        if (\method_exists($user, 'canAny')) {
            return (bool) $user->canAny($permissions);
        }
        if (\method_exists($user, 'can')) {
            foreach ($permissions as $permission) {
                if ($user->can((string) $permission)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function tenantIdentifier(): ?string
    {
        // وابستگی نرم به موتور tenancy
        if (!\class_exists(\Tavaan\Engine\Tenancy\TenantContext::class)) {
            return null;
        }
        $tenant = \Tavaan\Engine\Tenancy\TenantContext::get();

        return $tenant instanceof TenantInterface ? $tenant->identifier() : null;
    }
}

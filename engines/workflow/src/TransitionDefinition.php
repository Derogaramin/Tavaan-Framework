<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

/**
 * تَوان | Tavaan — Workflow
 * TransitionDefinition — تعریف تغییرناپذیر یک گذار (DOC-071 §2/§3).
 *
 * Guard: شرط اجرا (callable یا رشتهٔ `can:<permission>` برای تعاریف JSON).
 * Action: عمل هنگام گذار (callable یا `event:<name>` برای dispatch رشته‌ای).
 * Permission: مجوز RBAC مستقل از guard (بررسی کاربر جاری).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final readonly class TransitionDefinition
{
    /**
     * @param list<callable|string> $guards
     * @param list<callable|string> $actions
     */
    public function __construct(
        public string $id,
        public string $from,
        public string $to,
        public array $guards = [],
        public array $actions = [],
        public ?string $permission = null,
        public array $meta = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $guards = [];
        $rawGuard = $data['guard'] ?? null;
        if (\is_string($rawGuard) && $rawGuard !== '') {
            $guards[] = $rawGuard;
        } elseif (\is_array($rawGuard)) {
            foreach ($rawGuard as $g) {
                if (\is_string($g) || \is_callable($g)) {
                    $guards[] = $g;
                }
            }
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            from: (string) ($data['from'] ?? ''),
            to: (string) ($data['to'] ?? ''),
            guards: $guards,
            permission: isset($data['permission']) ? (string) $data['permission'] : null,
            meta: (array) ($data['meta'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $guards = [];
        foreach ($this->guards as $guard) {
            // فقط guardهای رشته‌ای قابل سریال‌سازی‌اند (closureها در JSON نمی‌آیند)
            if (\is_string($guard)) {
                $guards[] = $guard;
            }
        }

        return \array_filter([
            'id' => $this->id,
            'from' => $this->from,
            'to' => $this->to,
            'guard' => $guards === [] ? null : ($guards[0] ?? null),
            'permission' => $this->permission,
            'meta' => $this->meta === [] ? null : $this->meta,
        ], static fn (mixed $v): bool => $v !== null);
    }
}

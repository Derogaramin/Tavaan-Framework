<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * Condition — شرط خروجی دروازهٔ انحصاری (DOC-098 §3).
 */

namespace Modules\Bpms\Domain\ValueObjects;

/**
 * Condition — تغییرناپذیر: یا `expr` (عبارت شرط — با ExpressionEvaluator
 * امن ارزیابی می‌شود) یا `default=true` (مسیر پیش‌فرض). `next` مقصد است.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class Condition
{
    public function __construct(
        public ?string $expr,
        public bool $default,
        public string $next,
        public ?string $label = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $expr = isset($data['expr']) && \is_scalar($data['expr']) ? \trim((string) $data['expr']) : '';

        return new self(
            expr: $expr === '' ? null : $expr,
            default: (bool) ($data['default'] ?? false),
            next: (string) ($data['next'] ?? ''),
            label: isset($data['label']) && \is_scalar($data['label']) ? (string) $data['label'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return \array_filter([
            'expr' => $this->expr,
            'default' => $this->default ? true : null,
            'next' => $this->next,
            'label' => $this->label,
        ], static fn (mixed $v): bool => $v !== null);
    }
}

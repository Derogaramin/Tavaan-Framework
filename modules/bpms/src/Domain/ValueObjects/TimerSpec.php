<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * TimerSpec — تایمر/escalation روی یک عنصر (DOC-098 §3/§9).
 */

namespace Modules\Bpms\Domain\ValueObjects;

/**
 * TimerSpec — `{"on":"manager_approval","after":"P2D","action":"escalate"}`:
 * اگر عنصر `on` تا `after` (ISO-8601 یا انسانی) تمام نشد، اکشن ثبت‌شده
 * (`escalate` داخلی است) اجرا می‌شود. idempotent با escalated_at روی task.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class TimerSpec
{
    public function __construct(
        public string $on,
        public string $after,
        public string $action,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            on: (string) ($data['on'] ?? ''),
            after: (string) ($data['after'] ?? ''),
            action: (string) ($data['action'] ?? 'escalate'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['on' => $this->on, 'after' => $this->after, 'action' => $this->action];
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * HistoryEntry — یک ردیف تاریخچهٔ گذار (DOC-098 §2).
 */

namespace Modules\Bpms\Domain\Entities;

/**
 * HistoryEntry — VO تغییرناپذیر تاریخچه: from/to/transition/actor/trigger/
 * duration_ms (برای bottleneck و SLA در پایش — §8).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class HistoryEntry
{
    public function __construct(
        public int $id,
        public string $instanceId,
        public ?string $fromState,
        public string $toState,
        public string $transition,
        public ?int $actorId,
        public string $trigger,
        public string $occurredAt,
        public ?int $durationMs,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            instanceId: (string) ($row['instance_id'] ?? ''),
            fromState: isset($row['from_state']) ? (string) $row['from_state'] : null,
            toState: (string) ($row['to_state'] ?? ''),
            transition: (string) ($row['transition'] ?? ''),
            actorId: isset($row['actor_id']) ? (int) $row['actor_id'] : null,
            trigger: (string) ($row['trigger'] ?? 'system'),
            occurredAt: (string) ($row['occurred_at'] ?? ''),
            durationMs: isset($row['duration_ms']) ? (int) $row['duration_ms'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'instance_id' => $this->instanceId,
            'from_state' => $this->fromState,
            'to_state' => $this->toState,
            'transition' => $this->transition,
            'actor_id' => $this->actorId,
            'trigger' => $this->trigger,
            'occurred_at' => $this->occurredAt,
            'duration_ms' => $this->durationMs,
        ];
    }
}

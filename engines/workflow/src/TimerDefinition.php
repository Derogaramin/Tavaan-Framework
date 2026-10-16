<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

/**
 * تَوان | Tavaan — Workflow
 * TimerDefinition — رویداد زمان‌دار (DOC-071 §6).
 *
 * وقتی توکن وارد state برابر `on` می‌شود، تایمر با سررسید `now + after`
 * ثبت می‌گردد؛ در سررسید، توکن مستقیماً به `to` منتقل می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final readonly class TimerDefinition
{
    /**
     * @param string $after مدت: انسانی (`3 days`) یا ISO-8601 (`P3D`)
     */
    public function __construct(
        public string $id,
        public string $after,
        public string $on,
        public string $to,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            after: (string) ($data['after'] ?? ''),
            on: (string) ($data['on'] ?? ''),
            to: (string) ($data['to'] ?? ''),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'after' => $this->after,
            'on' => $this->on,
            'to' => $this->to,
        ];
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * SlaPolicy — سررسید SLA بر اساس اولویت (DOC-096 §3.3).
 */

namespace Modules\Crm\Domain\Services;

use Modules\Crm\Domain\Enums\TicketPriority;
use Tavaan\Support\Time\Clock;

/**
 * SlaPolicy — due = now + hours(priority).
 *
 * ⏱ زمان از Clock (منبع یگانه — قابل Fake در تست؛ DOC-110).
 *
 * @api 1.0
 */
final class SlaPolicy
{
    /** @param array<string, mixed> $config کلید config('crm.sla') — ساعت بر اساس اولویت */
    public function __construct(private readonly array $config = [])
    {
    }

    public function hoursFor(TicketPriority $priority): int
    {
        $hours = (int) ($this->config[$priority->value] ?? match ($priority) {
            TicketPriority::Urgent => 2,
            TicketPriority::High => 4,
            TicketPriority::Normal => 8,
            TicketPriority::Low => 24,
        });

        return \max(1, $hours);
    }

    public function dueAt(TicketPriority $priority, ?\DateTimeImmutable $from = null): \DateTimeImmutable
    {
        $from ??= Clock::utcNow();

        return $from->add(new \DateInterval('PT' . $this->hoursFor($priority) . 'H'));
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * TicketRepositoryInterface — پورت مخزن تیکت + پاسخ‌ها.
 */

namespace Modules\Crm\Domain\Repositories;

use Modules\Crm\Domain\Entities\Ticket;
use Modules\Crm\Domain\Entities\TicketReply;

/**
 * @api 1.0
 */
interface TicketRepositoryInterface
{
    public function save(Ticket $ticket): void;

    public function findById(string $id): ?Ticket;

    public function findByNumber(string $ticketNumber): ?Ticket;

    public function numberExists(string $ticketNumber): bool;

    /**
     * @param array{status?: string|null, priority?: string|null, assigned_to?: int|null, contact_id?: string|null, escalated?: bool|null, search?: string, mine?: int|null} $filters
     *
     * @return array{rows: list<Ticket>, total: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array;

    /** تیکت‌های بازِ سررسیدگذشته (SLA scan — شامل unassigned). @return list<Ticket> */
    public function openOverdue(\DateTimeImmutable $now, int $limit = 500): array;

    /** resolvedهای قدیمی‌تر از N روز (بسته‌شدن خودکار). @return list<Ticket> */
    public function resolvedOlderThan(\DateTimeImmutable $cutoff, int $limit = 200): array;

    /** تیکت‌های یک مخاطب. @return list<Ticket> */
    public function forContact(string $contactId): array;

    // ── پاسخ‌ها ───────────────────────────────────────────────────────────

    public function saveReply(TicketReply $reply): void;

    /** پاسخ‌های یک تیکت (صعودی) — internalها فقط برای agent. @return list<TicketReply> */
    public function repliesOf(string $ticketId, bool $includeInternal = true): array;

    // ── آمار (گزارش §6) ───────────────────────────────────────────────────

    /** @return array{total: int, open: int, pending: int, resolved: int, closed: int, escalated: int, avg_resolution_minutes: float, sla_compliance: float} */
    public function stats(): array;

    /** عملکرد کارشناس روی تیکت‌ها. @return array<int, array{assigned: int, resolved: int}> */
    public function agentStats(): array;
}

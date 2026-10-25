<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * OpportunityRepositoryInterface — پورت مخزن فرصت فروش.
 */

namespace Modules\Crm\Domain\Repositories;

use Modules\Crm\Domain\Entities\Opportunity;

/**
 * @api 1.0
 */
interface OpportunityRepositoryInterface
{
    public function save(Opportunity $opportunity): void;

    public function findById(string $id): ?Opportunity;

    /**
     * @param array{stage?: string|null, owner_id?: int|null, contact_id?: string|null, search?: string} $filters
     *
     * @return array{rows: list<Opportunity>, total: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array;

    /** فرصت‌های یک مخاطب. @return list<Opportunity> */
    public function forContact(string $contactId): array;

    /** همهٔ فرصت‌های باز (برای forecast). @return list<Opportunity> */
    public function openOpportunities(int $limit = 5000): array;

    /** شمارش وضعیت‌ها برای گزارش. @return array<string, int> stage → count */
    public function countByStage(): array;

    /** فرصت‌های بسته‌شده در بازه (won/lost). @return list<Opportunity> */
    public function closedBetween(?\DateTimeImmutable $from, ?\DateTimeImmutable $to): array;

    /** برد‌های یک بازه (revenue by period). @return list<Opportunity> */
    public function wonBetween(?\DateTimeImmutable $from, ?\DateTimeImmutable $to): array;
}

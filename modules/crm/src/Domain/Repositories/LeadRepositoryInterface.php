<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * LeadRepositoryInterface — پورت مخزن سرنخ.
 */

namespace Modules\Crm\Domain\Repositories;

use Modules\Crm\Domain\Entities\Lead;

/**
 * @api 1.0
 */
interface LeadRepositoryInterface
{
    public function save(Lead $lead): void;

    public function findById(string $id): ?Lead;

    /**
     * @param array{status?: string|null, owner_id?: int|null, source?: string|null, min_score?: int|null, search?: string} $filters
     *
     * @return array{rows: list<Lead>, total: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array;

    /** سرنخ‌های یک مخاطب. @return list<Lead> */
    public function forContact(string $contactId): array;

    /** @return list<Lead> */
    public function all(int $limit = 1000): array;

    /**
     * تجمیع منبع → آمار (DOC-096 §6: lead source effectiveness).
     *
     * @return array<string, array{total: int, qualified: int, converted: int, lost: int}>
     */
    public function statsBySource(): array;
}

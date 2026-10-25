<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * CampaignRepositoryInterface — پورت مخزن کمپین + گیرندگان.
 */

namespace Modules\Crm\Domain\Repositories;

use Modules\Crm\Domain\Entities\Campaign;

/**
 * @api 1.0
 */
interface CampaignRepositoryInterface
{
    public function save(Campaign $campaign): void;

    public function findById(string $id): ?Campaign;

    /**
     * @param array{status?: string|null, type?: string|null} $filters
     *
     * @return array{rows: list<Campaign>, total: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array;

    /** کمپین‌های زمان‌بندی‌شدهٔ سررسیدرسیده (cron). @return list<Campaign> */
    public function dueScheduled(\DateTimeImmutable $now, int $limit = 20): array;

    /** idempotency — آیا این مخاطب پیش‌تر در این کمپین دریافت کرده؟ */
    public function recipientExists(string $campaignId, string $contactId): bool;

    public function recordRecipient(string $campaignId, string $contactId, string $address, bool $success): void;
}

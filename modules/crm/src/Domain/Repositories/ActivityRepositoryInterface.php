<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ActivityRepositoryInterface — پورت مخزن فعالیت.
 */

namespace Modules\Crm\Domain\Repositories;

use Modules\Crm\Domain\Entities\Activity;

/**
 * @api 1.0
 */
interface ActivityRepositoryInterface
{
    public function save(Activity $activity): void;

    public function findById(string $id): ?Activity;

    /**
     * فعالیت‌های یک موجودیت (contact/lead/opportunity/ticket) — نزولی.
     *
     * @return list<Activity>
     */
    public function forSubject(?string $contactId = null, ?string $leadId = null, ?string $opportunityId = null, ?string $ticketId = null, int $limit = 200): array;

    /** شمارش فعالیت‌های یک سرنخ/مخاطب (engagement scoring). */
    public function countFor(?string $contactId = null, ?string $leadId = null): int;

    /** کارهای معوق/سررسید‌گذشتهٔ یک کاربر. @return list<Activity> */
    public function dueFor(int $userId, bool $includeOverdue = true, int $limit = 100): array;
}

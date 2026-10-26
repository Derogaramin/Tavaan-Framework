<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * InstanceRepositoryInterface — پورت نمونه‌های فرایند (DOC-098 §2).
 */

namespace Modules\Bpms\Domain\Repositories;

use Modules\Bpms\Domain\Entities\ProcessInstance;

/**
 * InstanceRepositoryInterface — ذخیره/بازیابی نمونه‌ها (bpms_process_instances).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
interface InstanceRepositoryInterface
{
    public function create(ProcessInstance $instance): void;

    /** ذخیرهٔ کامل وضعیت (status/current_state/variables/completed_at/mirror). */
    public function update(ProcessInstance $instance): void;

    public function findById(string $id): ?ProcessInstance;

    /**
     * فهرست صفحه‌بندی‌شده.
     *
     * @param array<string, mixed> $filters status/definition_key/initiator_id/business_key/search
     *
     * @return array{rows: list<ProcessInstance>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /** ردیف‌های خام برای پایش (بدون hydrate — KPIها). @return list<array<string, mixed>> */
    public function rawForMonitoring(?string $definitionKey = null, int $limit = 5000): array;

    /** شمارش نمونه‌های فعال یک تعریف (🔒 سقف DoS — DOC-071 §8). */
    public function countActiveForDefinition(string $definitionId): int;
}

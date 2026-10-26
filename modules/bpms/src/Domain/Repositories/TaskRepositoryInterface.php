<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * TaskRepositoryInterface — پورت کارهای انسانی (DOC-098 §2).
 */

namespace Modules\Bpms\Domain\Repositories;

use Modules\Bpms\Domain\Entities\Task;

/**
 * TaskRepositoryInterface — ذخیره/بازیابی کارها (bpms_tasks).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
interface TaskRepositoryInterface
{
    public function create(Task $task): void;

    /** ذخیرهٔ کامل وضعیت کار (status/assignee/form_data/ستون‌های زمانی). */
    public function update(Task $task): void;

    /**
     * تصاحب اتمیک — UPDATE … WHERE status IN (pending) یا (claimed AND claimed_by=me).
     *
     * @return bool آیا claim برنده شد؟ (رقابت → false = already_claimed)
     */
    public function claimAtomic(string $taskId, int $userId, string $claimedAt): bool;

    public function findById(string $id): ?Task;

    /** کارهای یک نمونه. @return list<Task> */
    public function forInstance(string $instanceId, ?string $status = null): array;

    /**
     * inbox کاربر — کارهای مستقیم (user:id) + کارهای نقش‌های کاربر
     * (role/group — قابل تصاحب) + کارهای `initiator` وقتی کاربر
     * آغازگر نمونه است.
     *
     * @param list<string> $roles
     *
     * @return list<Task>
     */
    public function inboxFor(int $userId, array $roles, int $limit = 200): array;

    /**
     * کارهای بازِ سررسیدگذشته و escalate-نشده (برای TimerService — §9).
     *
     * @return list<Task>
     */
    public function overdueOpen(string $nowIso, int $limit = 100): array;

    /**
     * فهرست صفحه‌بندی‌شده (پنل).
     *
     * @param array<string, mixed> $filters status/assignee/instance/overdue
     *
     * @return array{rows: list<Task>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /** ردیف‌های خام برای پایش SLA/workload. @return list<array<string, mixed>> */
    public function rawForMonitoring(?string $definitionKey = null, int $limit = 5000): array;

    /** لغو کارهای باز یک نمونه (terminate). @return int تعداد لغوشده */
    public function cancelOpenForInstance(string $instanceId): int;
}

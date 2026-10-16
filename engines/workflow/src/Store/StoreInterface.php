<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow\Store;

use Tavaan\Engine\Workflow\ProcessDefinition;

/**
 * تَوان | Tavaan — Workflow
 * StoreInterface — پایداری تعاریف/instanceها/taskها/history (DOC-071 §5).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
interface StoreInterface
{
    /** ذخیرهٔ نسخهٔ جدید تعریف (نسخه‌های قبلی غیرفعال می‌شوند). */
    public function saveWorkflow(ProcessDefinition $definition): string;

    /** یک رکورد workflow بر اساس شناسهٔ ردیف. @return array<string, mixed>|null */
    public function workflow(string $id): ?array;

    /** آخرین نسخهٔ فعال یک تعریف (null = نبود). @return array<string, mixed>|null */
    public function latestWorkflow(string $name): ?array;

    /** فهرست تعاریف (آخرین نسخهٔ هر نام). @return list<array<string, mixed>> */
    public function workflows(): array;

    /** @param array<string, mixed> $row */
    public function createInstance(array $row): void;

    /** @param array<string, mixed> $changes */
    public function updateInstance(string $id, array $changes): void;

    /** @return array<string, mixed>|null */
    public function findInstance(string $id): ?array;

    /** تعداد instanceهای فعال (running) یک workflow — محافظ DoS. */
    public function countActiveInstances(string $workflowId): int;

    /** همهٔ instanceهای running (برای TimerProcessor). @return list<array<string, mixed>> */
    public function runningInstances(int $limit = 500): array;

    /** @param array<string, mixed> $row */
    public function recordHistory(array $row): void;

    /** @return list<array<string, mixed>> */
    public function history(string $instanceId): array;

    /** @param array<string, mixed> $row */
    public function createTask(array $row): void;

    /** @param array<string, mixed> $changes */
    public function updateTask(string $id, array $changes): void;

    /** @return array<string, mixed>|null */
    public function findTask(string $id): ?array;

    /** @return list<array<string, mixed>> */
    public function tasksForInstance(string $instanceId, ?string $status = null): array;

    /** @return list<array<string, mixed>> */
    public function tasksForAssignee(string $assigneeId, ?string $status = null): array;
}

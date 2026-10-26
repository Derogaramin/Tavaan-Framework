<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * DefinitionRepositoryInterface — پورت تعاریف فرایند (DOC-098 §2).
 */

namespace Modules\Bpms\Domain\Repositories;

use Modules\Bpms\Domain\Entities\ProcessDefinition;

/**
 * DefinitionRepositoryInterface — ذخیره/بازیابی تعاریف (bpms_process_definitions).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
interface DefinitionRepositoryInterface
{
    public function save(ProcessDefinition $definition): void;

    public function findById(string $id): ?ProcessDefinition;

    /** نسخهٔ فعال (published + is_active) برای start — §5 findActive. */
    public function findActiveByKey(string $key): ?ProcessDefinition;

    /** آخرین نسخهٔ هر key صرف‌نظر از وضعیت. */
    public function latestVersionOf(string $key): ?ProcessDefinition;

    /**
     * فهرست صفحه‌بندی‌شده.
     *
     * @param array<string, mixed> $filters key/status/active/search
     *
     * @return array{rows: list<ProcessDefinition>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /** همهٔ نسخه‌های یک key (نزولی). @return list<ProcessDefinition> */
    public function versionsOf(string $key): array;

    /** غیرفعال‌سازی همهٔ نسخه‌های key به‌جز یک مورد (activate اتمیک). */
    public function deactivateOtherVersions(string $key, string $exceptId, string $updatedAt): void;
}

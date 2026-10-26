<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * HistoryRepositoryInterface — پورت تاریخچهٔ گذارها (DOC-098 §2).
 */

namespace Modules\Bpms\Domain\Repositories;

use Modules\Bpms\Domain\Entities\HistoryEntry;

/**
 * HistoryRepositoryInterface — append-only (bpms_history).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
interface HistoryRepositoryInterface
{
    public function record(HistoryEntry $entry): void;

    /**
     * تاریخچهٔ یک نمونه (صعودی — timeline).
     *
     * @return list<HistoryEntry>
     */
    public function forInstance(string $instanceId): array;

    /**
     * ردیف‌های خام برای پایش (bottleneck/میانگین).
     *
     * @return list<array<string, mixed>>
     */
    public function rawForMonitoring(?string $definitionKey = null, int $limit = 20000): array;
}

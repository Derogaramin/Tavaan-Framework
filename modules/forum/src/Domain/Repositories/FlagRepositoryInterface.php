<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * FlagRepositoryInterface — پورت صف moderation (DOC-094 §5).
 */

namespace Modules\Forum\Domain\Repositories;

use Modules\Forum\Domain\Entities\Flag;
use Modules\Forum\Domain\Enums\FlagStatus;

/**
 * @api 1.0
 */
interface FlagRepositoryInterface
{
    public function save(Flag $flag): void;

    public function findById(int $id): ?Flag;

    /** آیا این کاربر پیش‌تر روی این پست گزارش باز دارد؟ (flag_once_per_user) */
    public function hasOpenByUser(string $postId, int $userId): bool;

    /** تعداد گزارش‌های باز یک پست (auto_hide_threshold). */
    public function countOpenForPost(string $postId): int;

    /**
     * صفحه‌بندی صف بر اساس وضعیت.
     *
     * @return array{rows: list<Flag>, total: int}
     */
    public function paginate(FlagStatus $status, int $page, int $perPage): array;

    public function countByStatus(FlagStatus $status): int;

    public function deleteForPost(string $postId): void;
}

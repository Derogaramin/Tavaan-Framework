<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * DatabaseFaqItemRepository — آداپتر ماندگاری پرسش‌ها روی faq_items.
 */

namespace Modules\Faq\Infrastructure\Persistence;

use Modules\Content\Domain\Enums\ContentStatus;
use Modules\Faq\Domain\Entities\FaqItem;
use Modules\Faq\Domain\Repositories\FaqItemRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabaseFaqItemRepository — پیاده‌سازی FaqItemRepositoryInterface (بدون ORM).
 *
 * 🔒 ARCH-003: فقط Facade هسته (DB).
 * جست‌وجوی q با LIKE پارامتری (binding — بدون الحاق رشته) و escape برای % و _.
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
final class DatabaseFaqItemRepository implements FaqItemRepositoryInterface
{
    public function save(FaqItem $item): void
    {
        $row = $item->toRow();
        $exists = DB::table('faq_items')->where('id', $item->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('faq_items')->where('id', $item->id())->update($row);

            return;
        }

        DB::table('faq_items')->insert($row);
    }

    public function findById(string $id): ?FaqItem
    {
        $row = DB::table('faq_items')->where('id', $id)->first();

        return $row === null ? null : FaqItem::fromRow($row);
    }

    /** @return list<FaqItem> */
    public function publishedAll(): array
    {
        $rows = DB::table('faq_items')
            ->where('status', ContentStatus::Published->value)
            ->whereNull('deleted_at')
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return \array_map(static fn (array $row): FaqItem => FaqItem::fromRow($row), $rows);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{rows: list<FaqItem>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('faq_items');
        $rows = DB::table('faq_items');

        if (!($filters['with_trashed'] ?? false)) {
            $count->whereNull('deleted_at');
            $rows->whereNull('deleted_at');
        } elseif ((bool) ($filters['only_trashed'] ?? false)) {
            $count->whereNotNull('deleted_at');
            $rows->whereNotNull('deleted_at');
        }

        $status = $filters['status'] ?? null;
        if (\is_string($status) && $status !== '' && ContentStatus::tryFrom($status) !== null) {
            $count->where('status', $status);
            $rows->where('status', $status);
        }

        $categoryId = $filters['category_id'] ?? null;
        if (\is_string($categoryId) && $categoryId !== '') {
            $count->where('category_id', $categoryId);
            $rows->where('category_id', $categoryId);
        }

        $q = \trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . \str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $count->where('question', 'like', $like);
            $rows->where('question', 'like', $like);
        }

        $total = $count->count();
        $records = $rows->orderBy('sort_order', 'asc')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return [
            'rows' => \array_map(static fn (array $row): FaqItem => FaqItem::fromRow($row), $records),
            'total' => $total,
        ];
    }

    public function delete(string $id): void
    {
        DB::table('faq_items')->where('id', $id)->delete();
    }

    public function incrementHelpful(string $id, bool $yes): void
    {
        DB::table('faq_items')->where('id', $id)->increment($yes ? 'helpful_yes' : 'helpful_no');
    }

    public function countPublished(): int
    {
        return DB::table('faq_items')
            ->where('status', ContentStatus::Published->value)
            ->whereNull('deleted_at')
            ->count();
    }

    public function countByCategory(string $categoryId): int
    {
        return DB::table('faq_items')
            ->where('category_id', $categoryId)
            ->whereNull('deleted_at')
            ->count();
    }
}

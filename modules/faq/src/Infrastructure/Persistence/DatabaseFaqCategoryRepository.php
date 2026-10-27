<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * DatabaseFaqCategoryRepository — آداپتر ماندگاری دسته‌ها روی faq_categories.
 */

namespace Modules\Faq\Infrastructure\Persistence;

use Modules\Faq\Domain\Entities\FaqCategory;
use Modules\Faq\Domain\Repositories\FaqCategoryRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabaseFaqCategoryRepository — پیاده‌سازی FaqCategoryRepositoryInterface (بدون ORM).
 *
 * 🔒 ARCH-003: فقط Facade هسته (DB).
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
final class DatabaseFaqCategoryRepository implements FaqCategoryRepositoryInterface
{
    public function save(FaqCategory $category): void
    {
        $row = $category->toRow();
        $exists = DB::table('faq_categories')->where('id', $category->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('faq_categories')->where('id', $category->id())->update($row);

            return;
        }

        DB::table('faq_categories')->insert($row);
    }

    public function findById(string $id): ?FaqCategory
    {
        $row = DB::table('faq_categories')->where('id', $id)->first();

        return $row === null ? null : FaqCategory::fromRow($row);
    }

    public function findBySlug(string $slug): ?FaqCategory
    {
        $row = DB::table('faq_categories')
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : FaqCategory::fromRow($row);
    }

    public function slugExists(string $slug, ?string $exceptId = null): bool
    {
        $query = DB::table('faq_categories')->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /** @return list<FaqCategory> */
    public function all(): array
    {
        $rows = DB::table('faq_categories')
            ->whereNull('deleted_at')
            ->orderBy('sort_order', 'asc')
            ->orderBy('title', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return \array_map(static fn (array $row): FaqCategory => FaqCategory::fromRow($row), $rows);
    }

    public function countAll(): int
    {
        return DB::table('faq_categories')->whereNull('deleted_at')->count();
    }
}

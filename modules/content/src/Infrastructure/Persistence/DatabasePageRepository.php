<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * DatabasePageRepository — آداپتر ماندگاری صفحات روی content_pages.
 */

namespace Modules\Content\Infrastructure\Persistence;

use Modules\Content\Domain\Entities\Page;
use Modules\Content\Domain\Enums\ContentStatus;
use Modules\Content\Domain\Repositories\PageRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabasePageRepository — پیاده‌سازی PageRepositoryInterface (بدون ORM).
 *
 * 🔒 ARCH-003: فقط Facade هسته (DB).
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class DatabasePageRepository implements PageRepositoryInterface
{
    public function save(Page $page): void
    {
        $row = $page->toRow();
        $exists = DB::table('content_pages')->where('id', $page->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('content_pages')->where('id', $page->id())->update($row);

            return;
        }

        DB::table('content_pages')->insert($row);
    }

    public function findById(string $id): ?Page
    {
        $row = DB::table('content_pages')->where('id', $id)->first();

        return $row === null ? null : Page::fromRow($row);
    }

    public function findBySlug(string $slug, bool $withTrashed = false): ?Page
    {
        $query = DB::table('content_pages')->where('slug', $slug);
        if (!$withTrashed) {
            $query->whereNull('deleted_at');
        }

        $row = $query->first();

        return $row === null ? null : Page::fromRow($row);
    }

    public function slugExists(string $slug, ?string $exceptId = null): bool
    {
        $query = DB::table('content_pages')->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('content_pages');
        $rows = DB::table('content_pages');

        if (!($filters['with_trashed'] ?? false)) {
            $count->whereNull('deleted_at');
            $rows->whereNull('deleted_at');
        }

        $status = $filters['status'] ?? null;
        if (\is_string($status) && $status !== '' && ContentStatus::tryFrom($status) !== null) {
            $count->where('status', $status);
            $rows->where('status', $status);
        }

        $authorId = $filters['author_id'] ?? null;
        if ($authorId !== null && (int) $authorId > 0) {
            $count->where('author_id', (int) $authorId);
            $rows->where('author_id', (int) $authorId);
        }

        $total = $count->count();
        $records = $rows->orderByDesc('created_at')->orderByDesc('id')
            ->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return [
            'rows' => \array_map(static fn (array $row): Page => Page::fromRow($row), $records),
            'total' => $total,
        ];
    }

    public function latestPublished(int $limit): array
    {
        $limit = \max(1, \min(100, $limit));

        $rows = DB::table('content_pages')
            ->where('status', ContentStatus::Published->value)
            ->whereNull('deleted_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return \array_map(static fn (array $row): Page => Page::fromRow($row), $rows);
    }

    public function incrementViews(string $id, int $by = 1): void
    {
        DB::table('content_pages')->where('id', $id)->increment('view_count', \max(1, $by));
    }

    public function delete(string $id): void
    {
        DB::table('content_pages')->where('id', $id)->delete();
    }

    public function countPublished(): int
    {
        return DB::table('content_pages')
            ->where('status', ContentStatus::Published->value)
            ->whereNull('deleted_at')
            ->count();
    }
}

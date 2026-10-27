<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * DatabasePollRepository — آداپتر ماندگاری نظرسنجی‌ها روی poll_polls.
 */

namespace Modules\Poll\Infrastructure\Persistence;

use Modules\Poll\Domain\Entities\Poll;
use Modules\Poll\Domain\Enums\PollStatus;
use Modules\Poll\Domain\Repositories\PollRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabasePollRepository — پیاده‌سازی PollRepositoryInterface (بدون ORM).
 *
 * 🔒 ARCH-003: فقط Facade هسته (DB).
 * فیلتر پنجرهٔ زمانی (starts_at/ends_at) در PHP و با Poll::isVotable —
 * کوئری فقط ANDهای ساده می‌گیرد (بدون تکیه بر closure/orWhere).
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
final class DatabasePollRepository implements PollRepositoryInterface
{
    public function save(Poll $poll): void
    {
        $row = $poll->toRow();
        $exists = DB::table('poll_polls')->where('id', $poll->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('poll_polls')->where('id', $poll->id())->update($row);

            return;
        }

        DB::table('poll_polls')->insert($row);
    }

    public function findById(string $id): ?Poll
    {
        $row = DB::table('poll_polls')->where('id', $id)->first();

        return $row === null ? null : Poll::fromRow($row);
    }

    public function findBySlug(string $slug, bool $withTrashed = false): ?Poll
    {
        $query = DB::table('poll_polls')->where('slug', $slug);
        if (!$withTrashed) {
            $query->whereNull('deleted_at');
        }

        $row = $query->first();

        return $row === null ? null : Poll::fromRow($row);
    }

    public function slugExists(string $slug, ?string $exceptId = null): bool
    {
        $query = DB::table('poll_polls')->where('slug', $slug);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('poll_polls');
        $rows = DB::table('poll_polls');

        if (!($filters['with_trashed'] ?? false)) {
            $count->whereNull('deleted_at');
            $rows->whereNull('deleted_at');
        }

        $status = $filters['status'] ?? null;
        if (\is_string($status) && $status !== '' && PollStatus::tryFrom($status) !== null) {
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
            'rows' => \array_map(static fn (array $row): Poll => Poll::fromRow($row), $records),
            'total' => $total,
        ];
    }

    public function listOpen(int $limit): array
    {
        $limit = \max(1, \min(100, $limit));

        $rows = DB::table('poll_polls')
            ->where('status', PollStatus::Open->value)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $open = [];
        foreach ($rows as $row) {
            $poll = Poll::fromRow($row);
            if ($poll->isVotable()) {
                $open[] = $poll;
                if (\count($open) >= $limit) {
                    break;
                }
            }
        }

        return $open;
    }

    public function delete(string $id): void
    {
        DB::table('poll_polls')->where('id', $id)->delete();
    }

    public function countOpen(): int
    {
        return DB::table('poll_polls')
            ->where('status', PollStatus::Open->value)
            ->whereNull('deleted_at')
            ->count();
    }

    public function countAll(): int
    {
        return DB::table('poll_polls')->whereNull('deleted_at')->count();
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * DatabaseCommentStore — پیاده‌سازی عمومی پورت نظرات روی جدول comment_comments.
 */

namespace Modules\Comment\Infrastructure\Persistence;

use Modules\Comment\Contracts\CommentStoreInterface;
use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\Enums\CommentStatus;
use Tavaan\Core\Facade\Facades\DB;

/**
 * DatabaseCommentStore — آداپتر پیش‌فرض CommentStoreInterface (حالت standalone).
 *
 * جدول پلی‌مورفیک `comment_comments` برای مصرف مستقیم ماژول نظر است؛
 * ماژول‌های دارای جدول اختصاصی (مانند blog) آداپتر خودشان را دارند
 * (DatabaseCommentStore در blog روی blog_comments — DOC-091 §2).
 *
 * 🔒 ARCH-003: فقط Facade هسته (DB).
 *
 * @api 1.0
 * @see modules/comment/README.md
 */
final class DatabaseCommentStore implements CommentStoreInterface
{
    public function save(Comment $comment): void
    {
        $row = $this->toRow($comment);
        $exists = DB::table('comment_comments')->where('id', $comment->id)->exists();

        if ($exists) {
            DB::table('comment_comments')->where('id', $comment->id)->update($row);

            return;
        }

        DB::table('comment_comments')->insert($row);
    }

    public function findById(string $id): ?Comment
    {
        $row = DB::table('comment_comments')->where('id', $id)->first();

        return $row === null ? null : self::fromRow($row);
    }

    public function forSubject(string $commentableType, string $commentableId, ?CommentStatus $status = null): array
    {
        $query = DB::table('comment_comments')
            ->where('commentable_type', $commentableType)
            ->where('commentable_id', $commentableId);

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $rows = $query->orderBy('created_at')->orderBy('id')->get();

        return \array_map(static fn (array $row): Comment => self::fromRow($row), $rows);
    }

    public function updateStatus(string $id, CommentStatus $status): void
    {
        DB::table('comment_comments')->where('id', $id)->update([
            'status' => $status->value,
            'updated_at' => $this->utcNow(),
        ]);
    }

    public function delete(string $id): void
    {
        // فرزندانِ نظر حذف‌شده → ریشه (یتیم‌ها در ThreadBuilder به ریشه تبدیل می‌شوند)
        DB::table('comment_comments')->where('parent_id', $id)->update(['parent_id' => null]);
        DB::table('comment_comments')->where('id', $id)->delete();
    }

    public function countByStatus(CommentStatus $status): int
    {
        return DB::table('comment_comments')->where('status', $status->value)->count();
    }

    public function authorHasApprovedHistory(string $email): bool
    {
        return DB::table('comment_comments')
            ->where('author_email', \mb_strtolower($email))
            ->where('status', CommentStatus::Approved->value)
            ->exists();
    }

    // ─── افزوده‌های مدیریتی (فراتر از پورت) ──────────────────────────────

    /**
     * صفحه‌بندی همهٔ نظرات برای پنل مدیریت (فیلتر وضعیت/نوع subject).
     *
     * @return array{rows: list<Comment>, total: int}
     */
    public function paginateAll(?CommentStatus $status = null, string $type = '', int $page = 1, int $perPage = 20): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('comment_comments');
        $rows = DB::table('comment_comments');

        if ($status !== null) {
            $count->where('status', $status->value);
            $rows->where('status', $status->value);
        }
        if ($type !== '') {
            $count->where('commentable_type', $type);
            $rows->where('commentable_type', $type);
        }

        $total = $count->count();
        $records = $rows->orderByDesc('created_at')->orderByDesc('id')
            ->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return [
            'rows' => \array_map(static fn (array $row): Comment => self::fromRow($row), $records),
            'total' => $total,
        ];
    }

    /**
     * فهرست نوع‌های subject دارای نظر (برای فیلتر پنل).
     *
     * @return list<array{type: string, total: int, pending: int}>
     */
    public function typeStats(): array
    {
        $rows = DB::table('comment_comments')
            ->select('commentable_type')
            ->groupBy('commentable_type')
            ->get();

        $stats = [];
        foreach ($rows as $row) {
            $type = (string) $row['commentable_type'];
            $stats[] = [
                'type' => $type,
                'total' => DB::table('comment_comments')->where('commentable_type', $type)->count(),
                'pending' => DB::table('comment_comments')
                    ->where('commentable_type', $type)
                    ->where('status', CommentStatus::Pending->value)
                    ->count(),
            ];
        }

        \usort($stats, static fn (array $a, array $b): int => $b['pending'] <=> $a['pending'] ?: $b['total'] <=> $a['total']);

        return $stats;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fromRow(array $row): Comment
    {
        return new Comment(
            id: (string) ($row['id'] ?? ''),
            commentableType: (string) ($row['commentable_type'] ?? ''),
            commentableId: (string) ($row['commentable_id'] ?? ''),
            authorName: (string) ($row['author_name'] ?? ''),
            authorEmail: (string) ($row['author_email'] ?? ''),
            body: (string) ($row['body'] ?? ''),
            status: CommentStatus::tryFrom((string) ($row['status'] ?? 'pending')) ?? CommentStatus::Pending,
            parentId: isset($row['parent_id']) && $row['parent_id'] !== null ? (string) $row['parent_id'] : null,
            createdAt: isset($row['created_at']) && $row['created_at'] !== null
                ? new \DateTimeImmutable((string) $row['created_at'])
                : null,
            userId: isset($row['user_id']) && $row['user_id'] !== null ? (string) $row['user_id'] : null,
        );
    }

    /** @return array<string, mixed> */
    private function toRow(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'commentable_type' => $comment->commentableType,
            'commentable_id' => $comment->commentableId,
            'parent_id' => $comment->parentId,
            'user_id' => $comment->userId !== null && \ctype_digit($comment->userId) ? (int) $comment->userId : null,
            'author_name' => $comment->authorName,
            'author_email' => \mb_strtolower($comment->authorEmail),
            'body' => $comment->body,
            'status' => $comment->status->value,
            'created_at' => ($comment->createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->format('Y-m-d H:i:s.v'),
            'updated_at' => $this->utcNow(),
        ];
    }

    private function utcNow(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
    }
}

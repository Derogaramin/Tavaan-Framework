<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * DatabaseShopCommentStore — آداپتر پورت نظرات ماژول comment روی shop_comments.
 */

namespace Modules\Shop\Infrastructure\Persistence;

use Modules\Comment\Contracts\CommentStoreInterface;
use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\Enums\CommentStatus;
use Tavaan\Core\Facade\Facades\DB;

/**
 * نقد و بررسی محصول (DOC-093 §8) — همان الگوی blog_comments با prefix shop_.
 *
 * @api 1.0
 */
final class DatabaseShopCommentStore implements CommentStoreInterface
{
    public function save(Comment $comment): void
    {
        $row = $this->toRow($comment);
        $exists = DB::table('shop_comments')->where('id', $comment->id)->exists();

        if ($exists) {
            DB::table('shop_comments')->where('id', $comment->id)->update($row);

            return;
        }

        DB::table('shop_comments')->insert($row);
    }

    public function findById(string $id): ?Comment
    {
        $row = DB::table('shop_comments')->where('id', $id)->first();

        return $row === null ? null : self::fromRow($row);
    }

    public function forSubject(string $commentableType, string $commentableId, ?CommentStatus $status = null): array
    {
        $query = DB::table('shop_comments')
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
        DB::table('shop_comments')->where('id', $id)->update(['status' => $status->value]);
    }

    public function delete(string $id): void
    {
        DB::table('shop_comments')->where('parent_id', $id)->update(['parent_id' => null]);
        DB::table('shop_comments')->where('id', $id)->delete();
    }

    public function countByStatus(CommentStatus $status): int
    {
        return DB::table('shop_comments')->where('status', $status->value)->count();
    }

    public function authorHasApprovedHistory(string $email): bool
    {
        return DB::table('shop_comments')
            ->where('author_email', \mb_strtolower($email))
            ->where('status', CommentStatus::Approved->value)
            ->exists();
    }

    /**
     * @return array{rows: list<Comment>, total: int}
     */
    public function paginateAll(?CommentStatus $status, int $page, int $perPage): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('shop_comments');
        $rows = DB::table('shop_comments');
        if ($status !== null) {
            $count->where('status', $status->value);
            $rows->where('status', $status->value);
        }

        $total = $count->count();
        $data = $rows->orderByDesc('created_at')->orderByDesc('id')->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(static fn (array $row): Comment => self::fromRow($row), $data),
            'total' => $total,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): Comment
    {
        return new Comment(
            id: (string) ($row['id'] ?? ''),
            commentableType: (string) ($row['commentable_type'] ?? ''),
            commentableId: (string) ($row['commentable_id'] ?? ''),
            authorName: (string) ($row['author_name'] ?? ''),
            authorEmail: (string) ($row['author_email'] ?? ''),
            body: (string) ($row['body'] ?? ''),
            status: CommentStatus::tryFrom((string) ($row['status'] ?? 'pending')) ?? CommentStatus::Pending,
            parentId: isset($row['parent_id']) && $row['parent_id'] !== null && $row['parent_id'] !== ''
                ? (string) $row['parent_id']
                : null,
            createdAt: isset($row['created_at']) && \is_string($row['created_at']) && $row['created_at'] !== ''
                ? new \DateTimeImmutable($row['created_at'], new \DateTimeZone('UTC'))
                : null,
        );
    }

    /** @return array<string, mixed> */
    private function toRow(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'commentable_type' => $comment->commentableType,
            'commentable_id' => $comment->commentableId,
            'author_name' => $comment->authorName,
            'author_email' => \mb_strtolower($comment->authorEmail),
            'body' => $comment->body,
            'parent_id' => $comment->parentId,
            'status' => $comment->status->value,
            'created_at' => ($comment->createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ];
    }
}

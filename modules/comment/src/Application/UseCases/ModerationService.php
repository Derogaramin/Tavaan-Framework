<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * ModerationService — تأیید/اسپم/حذف نظرات + آمار پنل.
 */

namespace Modules\Comment\Application\UseCases;

use Modules\Comment\Contracts\CommentStoreInterface;
use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Comment\Infrastructure\Persistence\DatabaseCommentStore;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Event;

/**
 * ModerationService — UseCase مدیریت نظرات (پنل moderation).
 *
 * هر تغییر وضعیت رویداد `comment.status.changed` منتشر می‌کند (contract
 * ماژول — شنونده‌ها: اعلان‌ها/ایندکس جست‌وجو/آمار).
 *
 * @api 1.0
 * @see modules/comment/README.md
 */
final class ModerationService
{
    public function __construct(private readonly CommentStoreInterface $store)
    {
    }

    public function approve(string $id, ?int $actorId = null): Comment
    {
        return $this->changeStatus($id, CommentStatus::Approved, $actorId);
    }

    public function markSpam(string $id, ?int $actorId = null): Comment
    {
        return $this->changeStatus($id, CommentStatus::Spam, $actorId);
    }

    /** بازگردانی به صف بررسی. */
    public function unapprove(string $id, ?int $actorId = null): Comment
    {
        return $this->changeStatus($id, CommentStatus::Pending, $actorId);
    }

    public function delete(string $id, ?int $actorId = null): void
    {
        $comment = $this->findOr404($id);
        $this->store->delete($id);

        Event::dispatch('comment.deleted', [
            'comment_id' => $comment->id,
            'commentable_type' => $comment->commentableType,
            'commentable_id' => $comment->commentableId,
            'deleted_by' => $actorId,
        ]);
    }

    /**
     * آمار کلی برای ویجت داشبورد.
     *
     * @return array{pending: int, approved: int, spam: int, total: int}
     */
    public function stats(): array
    {
        $pending = $this->store->countByStatus(CommentStatus::Pending);
        $approved = $this->store->countByStatus(CommentStatus::Approved);
        $spam = $this->store->countByStatus(CommentStatus::Spam);

        return [
            'pending' => $pending,
            'approved' => $approved,
            'spam' => $spam,
            'total' => $pending + $approved + $spam,
        ];
    }

    /**
     * صفحه‌بندی برای پنل (فقط store عمومی — صف moderation سراسری).
     *
     * @return array{rows: list<Comment>, total: int}
     */
    public function paginate(?CommentStatus $status, string $type, int $page, int $perPage = 20): array
    {
        if (!$this->store instanceof DatabaseCommentStore) {
            return ['rows' => [], 'total' => 0];
        }

        return $this->store->paginateAll($status, $type, $page, $perPage);
    }

    /** @return list<array{type: string, total: int, pending: int}> */
    public function typeStats(): array
    {
        if (!$this->store instanceof DatabaseCommentStore) {
            return [];
        }

        return $this->store->typeStats();
    }

    public function findOr404(string $id): Comment
    {
        $comment = $this->store->findById($id);
        if ($comment === null) {
            throw new RouteNotFoundException(messageId: 'comment::messages.not_found', context: ['id' => $id]);
        }

        return $comment;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function changeStatus(string $id, CommentStatus $status, ?int $actorId): Comment
    {
        $comment = $this->findOr404($id);
        $previous = $comment->status;

        if ($previous === $status) {
            return $comment;
        }

        $this->store->updateStatus($id, $status);

        Event::dispatch('comment.status.changed', [
            'comment_id' => $comment->id,
            'commentable_type' => $comment->commentableType,
            'commentable_id' => $comment->commentableId,
            'status' => $status->value,
            'previous_status' => $previous->value,
            'changed_by' => $actorId,
        ]);

        return $comment->withStatus($status);
    }
}

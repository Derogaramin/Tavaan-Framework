<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * CommentService — ثبت/moderation نظرات + رویدادها (DOC-091 §5).
 */

namespace Modules\Blog\Application\UseCases;

use Modules\Blog\Domain\Entities\Post;
use Modules\Comment\Contracts\CommentStoreInterface;
use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Comment\Domain\Services\CommentModerator;
use Modules\Blog\Domain\Events\CommentWasApproved;
use Modules\Blog\Domain\Events\CommentWasSubmitted;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * CommentService — ثبت نظر مهمان/کاربر، تأیید، اسپم و حذف.
 *
 * جریان (E2E-02): خواننده نظر می‌گذارد → CommentModerator وضعیت را تعیین
 * می‌کند → رویداد `blog.comment.submitted` → (تأیید مدیر) → `blog.comment.approved`.
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class CommentService
{
    /**
     * @param array<string, mixed> $config کلید config('blog.comments')
     */
    public function __construct(
        private readonly CommentStoreInterface $comments,
        private readonly CommentModerator $moderator,
        private readonly array $config = [],
    ) {
    }

    /**
     * ثبت نظر جدید روی مقاله.
     *
     * @param array<string, mixed> $input author_name, author_email, body, parent_id?
     */
    public function submit(Post $post, array $input, ?int $userId = null, ?string $userDisplayName = null, ?string $userEmail = null): Comment
    {
        if (!(bool) ($this->config['enabled'] ?? true)) {
            throw new ForbiddenException(messageId: 'blog::messages.comments_disabled');
        }
        if (!$post->commentsOpen()) {
            throw new ForbiddenException(messageId: 'blog::messages.comments_closed');
        }

        $isGuest = $userId === null;
        if ($isGuest && !(bool) ($this->config['guest_allowed'] ?? true)) {
            throw new ForbiddenException(messageId: 'blog::messages.comments_guest_forbidden');
        }

        $rules = $isGuest
            ? [
                'author_name' => ['required', 'string', 'max:100'],
                'author_email' => ['required', 'email', 'max:190'],
                'body' => ['required', 'string', 'min:2', 'max:5000'],
                'parent_id' => ['nullable', 'string', 'size:26'],
            ]
            : [
                'body' => ['required', 'string', 'min:2', 'max:5000'],
                'parent_id' => ['nullable', 'string', 'size:26'],
            ];

        $validated = Validator::validate($input, $rules, attributes: [
            'author_name' => 'نام',
            'author_email' => 'ایمیل',
            'body' => 'متن نظر',
        ]);

        $authorName = $isGuest
            ? \trim((string) $validated['author_name'])
            : ($userDisplayName ?? 'کاربر');
        $authorEmail = $isGuest
            ? \mb_strtolower(\trim((string) $validated['author_email']))
            : \mb_strtolower($userEmail ?? '');

        // والد باید وجود داشته باشد و به همان مقاله تعلق داشته باشد (جلوگیری از تزریق)
        $parentId = isset($validated['parent_id']) && \is_string($validated['parent_id']) && $validated['parent_id'] !== ''
            ? $validated['parent_id']
            : null;
        if ($parentId !== null) {
            $parent = $this->comments->findById($parentId);
            if ($parent === null
                || $parent->commentableId !== $post->id()
                || $parent->commentableType !== $post->commentableType()) {
                throw new ValidationException(
                    errors: ['parent_id' => ['نظر والد معتبر نیست.']],
                    messageId: 'blog::messages.comment_invalid_parent',
                );
            }
        }

        $candidate = Comment::submit(
            commentableType: $post->commentableType(),
            commentableId: $post->commentableId(),
            authorName: $authorName,
            authorEmail: $authorEmail,
            body: \trim((string) $validated['body']),
            parentId: $parentId,
            userId: $userId !== null ? (string) $userId : null,
        );

        $status = $this->moderator->decide(
            $candidate,
            authorHasApprovedHistory: $this->comments->authorHasApprovedHistory($authorEmail),
        );
        $comment = $candidate->withStatus($status);

        $this->comments->save($comment);

        Event::dispatch((new CommentWasSubmitted(
            commentId: $comment->id,
            postId: $post->id(),
            postSlug: $post->slug(),
            authorName: $comment->authorName,
            authorEmail: $comment->authorEmail,
            status: $status->value,
        ))->eventName(), [
            'comment_id' => $comment->id,
            'post_id' => $post->id(),
            'post_slug' => $post->slug(),
            'author_name' => $comment->authorName,
            'author_email' => $comment->authorEmail,
            'status' => $status->value,
        ]);

        return $comment;
    }

    /** تأیید نظر — رویداد `blog.comment.approved` (DOC-091 §5). */
    public function approve(string $commentId): Comment
    {
        $comment = $this->findOr404($commentId);

        return $this->changeStatus($comment, CommentStatus::Approved, dispatchApproved: true);
    }

    public function markSpam(string $commentId): Comment
    {
        return $this->changeStatus($this->findOr404($commentId), CommentStatus::Spam);
    }

    public function unapprove(string $commentId): Comment
    {
        return $this->changeStatus($this->findOr404($commentId), CommentStatus::Pending);
    }

    public function delete(string $commentId): void
    {
        $comment = $this->findOr404($commentId);
        $this->comments->delete($comment->id);

        Event::dispatch('blog.comment.deleted', [
            'comment_id' => $comment->id,
            'post_id' => $comment->commentableId,
        ]);
    }

    public function findOr404(string $commentId): Comment
    {
        $comment = $this->comments->findById($commentId);
        if ($comment === null) {
            throw new RouteNotFoundException(messageId: 'blog::messages.comment_not_found', context: ['id' => $commentId]);
        }

        return $comment;
    }

    private function changeStatus(Comment $comment, CommentStatus $status, bool $dispatchApproved = false): Comment
    {
        if ($comment->status === $status) {
            return $comment;
        }

        $this->comments->updateStatus($comment->id, $status);
        $updated = $comment->withStatus($status);

        if ($dispatchApproved && $status === CommentStatus::Approved) {
            Event::dispatch((new CommentWasApproved(
                commentId: $updated->id,
                postId: $updated->commentableId,
                postSlug: '',
                commenterName: $updated->authorName,
                commenterEmail: $updated->authorEmail,
            ))->eventName(), [
                'comment_id' => $updated->id,
                'post_id' => $updated->commentableId,
                'commenter_name' => $updated->authorName,
                'commenter_email' => $updated->authorEmail,
            ]);
        }

        return $updated;
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * CommentService — ثبت/moderation نظرات خبر (DOC-092 §6).
 */

namespace Modules\News\Application\UseCases;

use Modules\Comment\Contracts\CommentStoreInterface;
use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Comment\Domain\Services\CommentModerator;
use Modules\News\Domain\Entities\Article;
use Modules\News\Domain\Events\NewsCommentWasApproved;
use Modules\News\Domain\Events\NewsCommentWasSubmitted;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * CommentService — جریان نظر خبر: ثبت → moderation → تأیید → اعلان.
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class CommentService
{
    /**
     * @param array<string, mixed> $config کلید config('news.comments')
     */
    public function __construct(
        private readonly CommentStoreInterface $comments,
        private readonly CommentModerator $moderator,
        private readonly array $config = [],
    ) {
    }

    /**
     * ثبت نظر جدید روی خبر.
     *
     * @param array<string, mixed> $input
     */
    public function submit(Article $article, array $input, ?int $userId = null, ?string $userDisplayName = null, ?string $userEmail = null): Comment
    {
        if (!(bool) ($this->config['enabled'] ?? true)) {
            throw new ForbiddenException(messageId: 'news::messages.comments_disabled');
        }
        if (!$article->commentsOpen()) {
            throw new ForbiddenException(messageId: 'news::messages.comments_closed');
        }

        $isGuest = $userId === null;
        if ($isGuest && !(bool) ($this->config['guest_allowed'] ?? true)) {
            throw new ForbiddenException(messageId: 'news::messages.comments_guest_forbidden');
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

        $authorName = $isGuest ? \trim((string) $validated['author_name']) : ($userDisplayName ?? 'کاربر');
        $authorEmail = $isGuest
            ? \mb_strtolower(\trim((string) $validated['author_email']))
            : \mb_strtolower($userEmail ?? '');

        $parentId = isset($validated['parent_id']) && \is_string($validated['parent_id']) && $validated['parent_id'] !== ''
            ? $validated['parent_id']
            : null;
        if ($parentId !== null) {
            $parent = $this->comments->findById($parentId);
            if ($parent === null
                || $parent->commentableId !== $article->id()
                || $parent->commentableType !== $article->commentableType()) {
                throw new ValidationException(
                    errors: ['parent_id' => ['نظر والد معتبر نیست.']],
                    messageId: 'news::messages.comment_invalid_parent',
                );
            }
        }

        $candidate = Comment::submit(
            commentableType: $article->commentableType(),
            commentableId: $article->commentableId(),
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

        $event = new NewsCommentWasSubmitted(
            commentId: $comment->id,
            articleId: $article->id(),
            articleSlug: $article->slug(),
            authorName: $comment->authorName,
            authorEmail: $comment->authorEmail,
            status: $status->value,
        );
        Event::dispatch($event->eventName(), $event->toPayload());

        return $comment;
    }

    public function approve(string $commentId): Comment
    {
        $comment = $this->findOr404($commentId);
        if ($comment->status === CommentStatus::Approved) {
            return $comment;
        }

        $this->comments->updateStatus($comment->id, CommentStatus::Approved);
        $updated = $comment->withStatus(CommentStatus::Approved);

        $event = new NewsCommentWasApproved(
            commentId: $updated->id,
            articleId: $updated->commentableId,
            articleSlug: '',
            commenterName: $updated->authorName,
            commenterEmail: $updated->authorEmail,
        );
        Event::dispatch($event->eventName(), $event->toPayload());

        return $updated;
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

        Event::dispatch('news.comment.deleted', [
            'comment_id' => $comment->id,
            'article_id' => $comment->commentableId,
        ]);
    }

    public function findOr404(string $commentId): Comment
    {
        $comment = $this->comments->findById($commentId);
        if ($comment === null) {
            throw new RouteNotFoundException(messageId: 'news::messages.comment_not_found', context: ['id' => $commentId]);
        }

        return $comment;
    }

    private function changeStatus(Comment $comment, CommentStatus $status): Comment
    {
        if ($comment->status === $status) {
            return $comment;
        }

        $this->comments->updateStatus($comment->id, $status);

        return $comment->withStatus($status);
    }
}

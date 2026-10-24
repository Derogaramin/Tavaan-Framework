<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ReviewService — نقد و بررسی محصول (DOC-093 §8: comment + media).
 */

namespace Modules\Shop\Application\UseCases;

use Modules\Comment\Contracts\CommentStoreInterface;
use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Comment\Domain\Services\CommentModerator;
use Modules\Shop\Domain\Entities\Product;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Security;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * ReviewService — همان الگوی CommentService وبلاگ روی پورت shop.
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class ReviewService
{
    /** @param array<string, mixed> $config کلید config('shop.reviews') */
    public function __construct(
        private readonly CommentStoreInterface $comments,
        private readonly CommentModerator $moderator,
        private readonly array $config = [],
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function submit(Product $product, array $input, ?int $userId = null, ?string $userDisplayName = null, ?string $userEmail = null): Comment
    {
        if (!(bool) ($this->config['enabled'] ?? true)) {
            throw new ForbiddenException(messageId: 'shop::messages.reviews_disabled');
        }
        if (!$product->commentsOpen()) {
            throw new ForbiddenException(messageId: 'shop::messages.reviews_closed');
        }

        $isGuest = $userId === null;
        if ($isGuest && !(bool) ($this->config['guest_allowed'] ?? true)) {
            throw new ForbiddenException(messageId: 'shop::messages.reviews_guest_forbidden');
        }

        $rules = $isGuest
            ? [
                'author_name' => ['required', 'string', 'max:100'],
                'author_email' => ['required', 'email', 'max:190'],
                'body' => ['required', 'string', 'min:2', 'max:5000'],
            ]
            : [
                'body' => ['required', 'string', 'min:2', 'max:5000'],
            ];

        $validated = Validator::validate($input, $rules, attributes: [
            'author_name' => 'نام',
            'author_email' => 'ایمیل',
            'body' => 'متن نقد',
        ]);

        // ذخیرهٔ متن پاک (plain) — دفاع در عمق علاوه بر escape در ویو
        $body = Security::sanitize(\trim((string) $validated['body']), 'plain');

        $candidate = Comment::submit(
            commentableType: $product->commentableType(),
            commentableId: $product->commentableId(),
            authorName: $isGuest ? \trim((string) $validated['author_name']) : ($userDisplayName ?? 'کاربر'),
            authorEmail: $isGuest ? \mb_strtolower(\trim((string) $validated['author_email'])) : \mb_strtolower($userEmail ?? ''),
            body: $body,
            userId: $userId !== null ? (string) $userId : null,
        );

        $status = $this->moderator->decide(
            $candidate,
            authorHasApprovedHistory: $this->comments->authorHasApprovedHistory($candidate->authorEmail),
        );
        $comment = $candidate->withStatus($status);
        $this->comments->save($comment);

        Event::dispatch('shop.review.submitted', [
            'comment_id' => $comment->id,
            'product_id' => $product->id(),
            'product_slug' => $product->slug(),
            'status' => $status->value,
        ]);

        return $comment;
    }

    public function approve(string $commentId): Comment
    {
        $comment = $this->findOr404($commentId);
        if ($comment->status === CommentStatus::Approved) {
            return $comment;
        }

        $this->comments->updateStatus($comment->id, CommentStatus::Approved);

        Event::dispatch('shop.review.approved', [
            'comment_id' => $comment->id,
            'product_id' => $comment->commentableId,
        ]);

        return $comment->withStatus(CommentStatus::Approved);
    }

    public function markSpam(string $commentId): Comment
    {
        $comment = $this->findOr404($commentId);
        $this->comments->updateStatus($comment->id, CommentStatus::Spam);

        return $comment->withStatus(CommentStatus::Spam);
    }

    public function delete(string $commentId): void
    {
        $comment = $this->findOr404($commentId);
        $this->comments->delete($comment->id);
    }

    public function findOr404(string $commentId): Comment
    {
        $comment = $this->comments->findById($commentId);
        if ($comment === null) {
            throw new RouteNotFoundException(messageId: 'shop::messages.review_not_found', context: ['id' => $commentId]);
        }

        return $comment;
    }
}

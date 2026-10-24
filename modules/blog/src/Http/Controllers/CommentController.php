<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * CommentController — ثبت نظر خواننده (DOC-091 §3: POST /blog/{slug}/comments).
 */

namespace Modules\Blog\Http\Controllers;

use Modules\Blog\Application\UseCases\CommentService;
use Modules\Blog\Application\UseCases\PostReadService;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Session;

/**
 * CommentController — ثبت نظر مهمان/کاربر + redirect با flash.
 *
 * CSRF توسط middleware سراسری بررسی می‌شود (@csrf در فرم ویو).
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class CommentController
{
    public function __construct(
        private readonly PostReadService $read,
        private readonly CommentService $commentService,
        private readonly UserReaderInterface $users,
    ) {
    }

    /** POST /blog/{slug}/comments */
    public function store(RequestInterface $request, string $slug): mixed
    {
        $post = $this->read->showPublished($slug, countView: false);

        $user = Auth::user();
        $userId = $user?->getAuthIdentifier();
        $snapshot = $userId === null ? null : $this->users->findById($userId);

        try {
            $comment = $this->commentService->submit(
                post: $post,
                input: [
                    'author_name' => $request->input('author_name'),
                    'author_email' => $request->input('author_email'),
                    'body' => $request->input('body'),
                    'parent_id' => $request->input('parent_id'),
                ],
                userId: $userId === null ? null : (int) $userId,
                userDisplayName: $snapshot?->name,
                userEmail: $snapshot?->email,
            );
        } catch (ValidationException $e) {
            Session::flash('blog_comment', [
                'status' => 'error',
                'errors' => $e->errors,
                'old' => ['author_name' => $request->input('author_name'), 'body' => $request->input('body')],
            ]);

            return redirect('/blog/' . $post->slug() . '#comment-form');
        }

        Session::flash('blog_comment', [
            'status' => 'ok',
            'message' => $comment->status === CommentStatus::Approved
                ? trans('comment::messages.submitted_approved')
                : trans('comment::messages.submitted'),
        ]);

        return redirect('/blog/' . $post->slug() . '#comments');
    }
}

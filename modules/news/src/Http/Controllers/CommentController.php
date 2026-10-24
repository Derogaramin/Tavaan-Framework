<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * CommentController — ثبت نظر خواننده روی خبر (DOC-092 §6).
 */

namespace Modules\News\Http\Controllers;

use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\News\Application\UseCases\ArticleReadService;
use Modules\News\Application\UseCases\CommentService;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Session;

/**
 * @api 1.0
 * @see modules/news/README.md
 */
final class CommentController
{
    public function __construct(
        private readonly ArticleReadService $read,
        private readonly CommentService $commentService,
        private readonly UserReaderInterface $users,
    ) {
    }

    /** POST /news/{slug}/comments */
    public function store(RequestInterface $request, string $slug): mixed
    {
        $article = $this->read->showPublished($slug, countView: false);

        $user = Auth::user();
        $userId = $user?->getAuthIdentifier();
        $snapshot = $userId === null ? null : $this->users->findById($userId);

        try {
            $comment = $this->commentService->submit(
                article: $article,
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
            Session::flash('news_comment', [
                'status' => 'error',
                'errors' => $e->errors,
                'old' => ['author_name' => $request->input('author_name'), 'body' => $request->input('body')],
            ]);

            return redirect('/news/' . $article->slug() . '#comment-form');
        }

        Session::flash('news_comment', [
            'status' => 'ok',
            'message' => $comment->status === CommentStatus::Approved
                ? trans('comment::messages.submitted_approved')
                : trans('comment::messages.submitted'),
        ]);

        return redirect('/news/' . $article->slug() . '#comments');
    }
}

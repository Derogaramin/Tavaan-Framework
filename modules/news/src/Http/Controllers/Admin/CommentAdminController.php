<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * CommentAdminController — moderation نظرات خبر.
 */

namespace Modules\News\Http\Controllers\Admin;

use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\News\Application\UseCases\CommentService;
use Modules\News\Domain\Repositories\ArticleRepositoryInterface;
use Modules\News\Infrastructure\Persistence\DatabaseNewsCommentStore;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * @api 1.0
 * @see modules/news/README.md
 */
final class CommentAdminController
{
    public function __construct(
        private readonly CommentService $comments,
        private readonly DatabaseNewsCommentStore $store,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    /** GET /admin/news/comments */
    public function index(RequestInterface $request): mixed
    {
        Gate::authorize('news.comment.view');

        $statusValue = (string) $request->query('status', '');
        $status = CommentStatus::tryFrom($statusValue);
        $page = \max(1, (int) $request->query('page', 1));

        $result = $this->store->paginateAll($status, $page, 20);

        $articleIds = [];
        foreach ($result['rows'] as $comment) {
            $articleIds[] = $comment->commentableId;
        }
        $articleTitles = [];
        foreach (\array_unique($articleIds) as $articleId) {
            $article = $this->articles->findById($articleId);
            if ($article !== null) {
                $articleTitles[$articleId] = $article->title();
            }
        }

        return view('news::admin.comments-index', [
            'comments' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'status' => $statusValue,
            'articleTitles' => $articleTitles,
            'pendingCount' => $this->store->countByStatus(CommentStatus::Pending),
        ]);
    }

    /** POST /admin/news/comments/{id}/approve */
    public function approve(string $id): mixed
    {
        Gate::authorize('news.comment.approve');
        $this->comments->approve($id);
        Session::flash('news_comment_ok', trans('news::messages.comment_approved'));

        return redirect('/admin/news/comments');
    }

    /** POST /admin/news/comments/{id}/spam */
    public function spam(string $id): mixed
    {
        Gate::authorize('news.comment.approve');
        $this->comments->markSpam($id);
        Session::flash('news_comment_ok', trans('news::messages.comment_marked_spam'));

        return redirect('/admin/news/comments');
    }

    /** DELETE /admin/news/comments/{id} */
    public function destroy(string $id): mixed
    {
        Gate::authorize('news.comment.delete');
        $this->comments->delete($id);
        Session::flash('news_comment_ok', trans('news::messages.comment_deleted'));

        return redirect('/admin/news/comments');
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * CommentAdminController — moderation نظرات (DOC-091 §3-5).
 */

namespace Modules\Blog\Http\Controllers\Admin;

use Modules\Blog\Application\UseCases\CommentService;
use Modules\Blog\Domain\Repositories\PostRepositoryInterface;
use Modules\Blog\Infrastructure\Persistence\DatabaseCommentStore;
use Modules\Comment\Domain\Enums\CommentStatus;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * CommentAdminController — فهرست/تأیید/اسپم/حذف نظرات.
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class CommentAdminController
{
    public function __construct(
        private readonly CommentService $comments,
        private readonly DatabaseCommentStore $store,
        private readonly PostRepositoryInterface $posts,
    ) {
    }

    /** GET /admin/blog/comments */
    public function index(RequestInterface $request): mixed
    {
        Gate::authorize('blog.comment.view');

        $statusValue = (string) $request->query('status', '');
        $status = CommentStatus::tryFrom($statusValue);
        $page = \max(1, (int) $request->query('page', 1));

        $result = $this->store->paginateAll($status, $page, 20);

        $postIds = [];
        foreach ($result['rows'] as $comment) {
            $postIds[] = $comment->commentableId;
        }
        $postTitles = [];
        foreach (\array_unique($postIds) as $postId) {
            $post = $this->posts->findById($postId);
            if ($post !== null) {
                $postTitles[$postId] = $post->title();
            }
        }

        return view('blog::admin.comments-index', [
            'comments' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'status' => $statusValue,
            'postTitles' => $postTitles,
            'pendingCount' => $this->store->countByStatus(CommentStatus::Pending),
        ]);
    }

    /** POST /admin/blog/comments/{id}/approve */
    public function approve(string $id): mixed
    {
        Gate::authorize('blog.comment.approve');

        $this->comments->approve($id);
        Session::flash('blog_comment_ok', trans('blog::messages.comment_approved'));

        return $this->back();
    }

    /** POST /admin/blog/comments/{id}/spam */
    public function spam(string $id): mixed
    {
        Gate::authorize('blog.comment.approve');

        $this->comments->markSpam($id);
        Session::flash('blog_comment_ok', trans('blog::messages.comment_marked_spam'));

        return $this->back();
    }

    /** DELETE /admin/blog/comments/{id} */
    public function destroy(string $id): mixed
    {
        Gate::authorize('blog.comment.delete');

        $this->comments->delete($id);
        Session::flash('blog_comment_ok', trans('blog::messages.comment_deleted'));

        return $this->back();
    }

    private function back(): mixed
    {
        return redirect('/admin/blog/comments');
    }
}

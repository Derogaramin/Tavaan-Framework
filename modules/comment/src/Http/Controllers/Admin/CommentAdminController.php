<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * CommentAdminController — صف moderation سراسری (/admin/comments).
 */

namespace Modules\Comment\Http\Controllers\Admin;

use Modules\Comment\Application\CommentableRegistry;
use Modules\Comment\Application\UseCases\ModerationService;
use Modules\Comment\Domain\Enums\CommentStatus;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * CommentAdminController — مدیریت نظرات standalone (صف بررسی، تأیید، اسپم، حذف).
 *
 * مجوزها: comment.view / comment.approve / comment.delete.
 *
 * @api 1.0
 * @see modules/comment/README.md
 */
final class CommentAdminController
{
    public function __construct(
        private readonly ModerationService $moderation,
        private readonly CommentableRegistry $registry,
    ) {
    }

    /** GET /admin/comments */
    public function index(RequestInterface $request): mixed
    {
        Gate::authorize('comment.view');

        $page = \max(1, (int) $request->query('page', 1));
        $statusParam = (string) $request->query('status', '');
        $type = \trim((string) $request->query('type', ''));

        $status = CommentStatus::tryFrom($statusParam);
        if ($statusParam !== '' && $status === null) {
            $status = null;
        }

        $result = $this->moderation->paginate($status, $type, $page);

        return view('comment::admin.comments-index', [
            'comments' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'status' => $statusParam,
            'type' => $type,
            'stats' => $this->moderation->stats(),
            'typeStats' => $this->moderation->typeStats(),
            'registry' => $this->registry,
            'errors' => Session::pull('comment_admin_errors') ?? [],
            'ok' => Session::pull('comment_admin_ok'),
        ]);
    }

    /** POST /admin/comments/{id}/approve */
    public function approve(string $id): mixed
    {
        Gate::authorize('comment.approve');

        try {
            $this->moderation->approve($id, actorId: (int) Auth::id());
            Session::flash('comment_admin_ok', trans('comment::messages.approved'));
        } catch (ValidationException|\Tavaan\Core\Exceptions\Http\RouteNotFoundException $e) {
            Session::flash('comment_admin_errors', ['comment' => [trans($e->messageId)]]);
        }

        return redirect('/admin/comments');
    }

    /** POST /admin/comments/{id}/spam */
    public function spam(string $id): mixed
    {
        Gate::authorize('comment.approve');

        try {
            $this->moderation->markSpam($id, actorId: (int) Auth::id());
            Session::flash('comment_admin_ok', trans('comment::messages.marked_spam'));
        } catch (ValidationException|\Tavaan\Core\Exceptions\Http\RouteNotFoundException $e) {
            Session::flash('comment_admin_errors', ['comment' => [trans($e->messageId)]]);
        }

        return redirect('/admin/comments');
    }

    /** POST /admin/comments/{id}/unapprove */
    public function unapprove(string $id): mixed
    {
        Gate::authorize('comment.approve');

        try {
            $this->moderation->unapprove($id, actorId: (int) Auth::id());
            Session::flash('comment_admin_ok', trans('comment::messages.moved_to_pending'));
        } catch (ValidationException|\Tavaan\Core\Exceptions\Http\RouteNotFoundException $e) {
            Session::flash('comment_admin_errors', ['comment' => [trans($e->messageId)]]);
        }

        return redirect('/admin/comments');
    }

    /** DELETE /admin/comments/{id} */
    public function destroy(string $id): mixed
    {
        Gate::authorize('comment.delete');

        try {
            $this->moderation->delete($id, actorId: (int) Auth::id());
            Session::flash('comment_admin_ok', trans('comment::messages.deleted'));
        } catch (\Tavaan\Core\Exceptions\Http\RouteNotFoundException $e) {
            Session::flash('comment_admin_errors', ['comment' => [trans($e->messageId)]]);
        }

        return redirect('/admin/comments');
    }
}

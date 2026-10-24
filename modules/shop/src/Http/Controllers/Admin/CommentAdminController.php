<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CommentAdminController — صف نقد و بررسی‌ها (/admin/shop/comments).
 */

namespace Modules\Shop\Http\Controllers\Admin;

use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Shop\Infrastructure\Persistence\DatabaseShopCommentStore;
use Modules\Shop\Application\UseCases\ReviewService;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class CommentAdminController
{
    public function __construct(
        private readonly ReviewService $reviews,
        private readonly DatabaseShopCommentStore $store,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('shop.comment.view');

        $statusParam = (string) $request->input('status', 'pending');
        $status = CommentStatus::tryFrom($statusParam);
        $page = \max(1, (int) $request->input('page', 1));

        $result = $this->store->paginateAll($status, $page, 20);

        return View::render('shop::admin.comments-index', [
            'comments' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'statusFilter' => $statusParam,
            'pendingCount' => $this->store->countByStatus(CommentStatus::Pending),
        ]);
    }

    public function approve(string $id): mixed
    {
        $this->authorize('shop.comment.approve');
        $this->reviews->approve($id);

        Session::flash('status', 'review_approved');



        return back();
    }

    public function spam(string $id): mixed
    {
        $this->authorize('shop.comment.approve');
        $this->reviews->markSpam($id);

        Session::flash('status', 'review_spammed');



        return back();
    }

    public function destroy(string $id): mixed
    {
        $this->authorize('shop.comment.delete');
        $this->reviews->delete($id);

        Session::flash('status', 'review_deleted');



        return redirect('/admin/shop/comments');
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'shop::messages.forbidden', context: ['permission' => $permission]);
        }
    }
}

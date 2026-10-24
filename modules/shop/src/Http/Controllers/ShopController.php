<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ShopController — ویترین عمومی (فهرست/جزئیات/دسته/جست‌وجو).
 */

namespace Modules\Shop\Http\Controllers;

use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Shop\Infrastructure\Persistence\DatabaseShopCommentStore;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Modules\Shop\Application\UseCases\CartService;
use Modules\Shop\Application\UseCases\CatalogService;
use Modules\Shop\Application\UseCases\ReviewService;
use Modules\Shop\Domain\Repositories\ProductRepositoryInterface;
use Modules\Shop\Domain\Repositories\ShopCategoryRepositoryInterface;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class ShopController
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly CartService $cartService,
        private readonly ProductRepositoryInterface $products,
        private readonly ShopCategoryRepositoryInterface $categories,
        private readonly CommentThreadBuilder $threads,
        private readonly ReviewService $reviews,
        private readonly DatabaseShopCommentStore $reviewStore,
        private readonly UserReaderInterface $users,
    ) {
    }

    /** فهرست محصولات — /shop */
    public function index(RequestInterface $request): mixed
    {
        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        if (($sort = (string) $request->input('sort', '')) !== '') {
            $filters['sort'] = $sort;
        }
        if (($featured = (string) $request->input('featured', '')) === '1') {
            $filters['featured'] = true;
        }

        $result = $this->catalog->listActive($filters, $page);

        return View::render('shop::front.index', [
            'products' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'categories' => $this->categories->all(),
            'filters' => $filters,
            'cart' => $this->currentCart(),
            'query' => '',
        ]);
    }

    /** جزئیات محصول + نقد و بررسی‌ها — /shop/products/{slug} */
    public function show(RequestInterface $request, string $slug): mixed
    {
        $product = $this->catalog->showActive($slug);
        $variants = $this->products->variantsOf($product->id());
        $related = $this->catalog->related($product);

        $approved = $this->threads->build(
            $this->reviewStore->forSubject($product->commentableType(), $product->id(), CommentStatus::Approved),
        );

        return View::render('shop::front.product', [
            'product' => $product,
            'variants' => $variants,
            'related' => $related,
            'thread' => $approved,
            'cart' => $this->currentCart(),
            'category' => $this->categories->findById($product->categoryId()),
            'body' => (string) $product->description(),
        ]);
    }

    /** دستهٔ محصول (با نوادگان) — /shop/categories/{slug} */
    public function category(RequestInterface $request, string $slug): mixed
    {
        $category = $this->categories->findBySlug($slug);
        if ($category === null || $category->id() === null) {
            throw RouteNotFoundException::forPath('GET', '/shop/categories/' . $slug);
        }

        $page = \max(1, (int) $request->input('page', 1));
        $result = $this->catalog->listActive([
            'category_ids' => $this->categories->withDescendants($category->id()),
            'sort' => (string) $request->input('sort', ''),
        ], $page);

        return View::render('shop::front.index', [
            'products' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'categories' => $this->categories->all(),
            'filters' => ['category' => $category->slug()],
            'cart' => $this->currentCart(),
            'category' => $category,
            'query' => '',
        ]);
    }

    /** جست‌وجوی فارسی — /shop/search (AC-SHOP-04) */
    public function search(RequestInterface $request): mixed
    {
        $query = \trim((string) $request->input('q', ''));
        $page = \max(1, (int) $request->input('page', 1));

        $result = $this->catalog->search($query, $page);

        return View::render('shop::front.index', [
            'products' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'categories' => $this->categories->all(),
            'filters' => [],
            'cart' => $this->currentCart(),
            'query' => $query,
        ]);
    }

    /** ثبت نقد و بررسی — POST /shop/products/{slug}/reviews */
    public function submitReview(RequestInterface $request, string $slug): mixed
    {
        $product = $this->catalog->showActive($slug, countView: false);

        $user = Auth::user();
        $displayName = null;
        $email = null;
        if ($user !== null) {
            $snapshot = $this->userSnapshot((int) $user->getAuthIdentifier());
            $displayName = (string) ($snapshot['display_name'] ?? 'کاربر');
            $email = (string) ($snapshot['email'] ?? '');
        }

        $comment = $this->reviews->submit(
            $product,
            input: [
                'author_name' => (string) $request->input('author_name', ''),
                'author_email' => (string) $request->input('author_email', ''),
                'body' => (string) $request->input('body', ''),
            ],
            userId: $user !== null ? (int) $user->getAuthIdentifier() : null,
            userDisplayName: $displayName,
            userEmail: $email,
        );

        Session::flash('status', $comment->status->value === 'approved' ? 'review_published' : 'review_pending');

        return redirect('/shop/products/' . $product->slug() . '#review-' . $comment->id);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function currentCart(): ?object
    {
        try {
            $user = Auth::user();
            $sessionId = $this->sessionId();

            return $this->cartService->current(
                $user !== null ? (int) $user->getAuthIdentifier() : null,
                $sessionId,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function sessionId(): ?string
    {
        try {
            $id = Session::id();
        } catch (\Throwable) {
            return null;
        }

        return \is_string($id) && $id !== '' ? $id : null;
    }

    /** @return array<string, mixed>|null */
    private function userSnapshot(int $userId): ?array
    {
        $snapshot = $this->users->findById($userId);
        if ($snapshot === null) {
            return null;
        }

        return ['display_name' => $snapshot->displayName(), 'email' => $snapshot->email];
    }
}

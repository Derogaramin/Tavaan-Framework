<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ApiShopController — API عمومی فروشگاه (DOC-093 §7).
 */

namespace Modules\Shop\Http\Controllers\Api;

use Modules\Shop\Application\UseCases\CartService;
use Modules\Shop\Application\UseCases\CatalogService;
use Modules\Shop\Application\UseCases\PlaceOrderService;
use Modules\Shop\Domain\Entities\Product;
use Modules\Shop\Domain\Repositories\OrderRepositoryInterface;
use Modules\Shop\Domain\Repositories\ProductRepositoryInterface;
use Modules\Shop\Domain\Repositories\ShopCategoryRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Session;

/**
 * ApiShopController — پاسخ‌های JSON برای SPA/اپ موبایل:
 *
 * ```
 * GET    /api/v1/shop/products          فهرست (filter/sort/paginate)
 * GET    /api/v1/shop/products/{slug}   جزئیات
 * GET    /api/v1/shop/categories        دسته‌ها
 * GET    /api/v1/shop/cart              سبد جاری
 * POST   /api/v1/shop/cart/items        افزودن
 * PUT    /api/v1/shop/cart/items/{id}   ویرایش تعداد
 * DELETE /api/v1/shop/cart/items/{id}   حذف
 * POST   /api/v1/shop/checkout          ثبت سفارش
 * GET    /api/v1/shop/orders            سفارش‌های کاربر
 * GET    /api/v1/shop/orders/{id}       جزئیات سفارش
 * POST   /api/v1/shop/orders/{id}/cancel لغو
 * ```
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class ApiShopController
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly CartService $carts,
        private readonly PlaceOrderService $placeOrder,
        private readonly ProductRepositoryInterface $products,
        private readonly ShopCategoryRepositoryInterface $categories,
        private readonly OrderRepositoryInterface $orders,
    ) {
    }

    /** GET /api/v1/shop/products */
    public function products(RequestInterface $request): array
    {
        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        if (($sort = (string) $request->input('sort', '')) !== '') {
            $filters['sort'] = $sort;
        }
        if (($q = (string) $request->input('q', '')) !== '') {
            $filters['search'] = $q;
        }
        if (($cat = (string) $request->input('category', '')) !== '') {
            $category = $this->categories->findBySlug($cat);
            if ($category !== null && $category->id() !== null) {
                $filters['category_ids'] = $this->categories->withDescendants($category->id());
            }
        }

        $result = $this->catalog->listActive($filters, $page);

        return [
            'data' => \array_map(fn (Product $p): array => $this->productArray($p), $result['rows']),
            'meta' => ['total' => $result['total'], 'page' => $result['page'], 'per_page' => $result['perPage']],
        ];
    }

    /** GET /api/v1/shop/products/{slug} */
    public function product(RequestInterface $request, string $slug): array
    {
        $product = $this->catalog->showActive($slug);
        $variants = $this->products->variantsOf($product->id());

        return [
            'data' => [
                ...$this->productArray($product),
                'description' => (string) $product->description(),
                'attributes' => $product->attributes(),
                'weight' => $product->weight(),
                'rating_avg' => $product->ratingAvg(),
                'view_count' => $product->viewCount(),
                'sold_count' => $product->soldCount(),
                'variants' => \array_map(static fn ($v): array => [
                    'id' => $v->id(),
                    'title' => $v->title(),
                    'sku' => $v->sku(),
                    'price_delta' => $v->priceDelta()->minor,
                    'stock' => $v->stock(),
                    'options' => $v->options(),
                ], $variants),
                'related' => \array_map(
                    fn (Product $p): array => ['id' => $p->id(), 'title' => $p->title(), 'slug' => $p->slug(), 'price' => $p->effectivePrice()->minor],
                    $this->catalog->related($product),
                ),
            ],
        ];
    }

    /** GET /api/v1/shop/categories */
    public function categories(): array
    {
        return [
            'data' => \array_map(static fn ($c): array => [
                'id' => $c->id(),
                'title' => $c->title(),
                'slug' => $c->slug(),
                'parent_id' => $c->parentId(),
            ], $this->categories->all()),
        ];
    }

    /** GET /api/v1/shop/cart */
    public function cart(): array
    {
        [$userId, $sessionId] = $this->identity();
        $cart = $this->carts->current($userId, $sessionId);

        return $this->cartArray($cart);
    }

    /** POST /api/v1/shop/cart/items */
    public function addToCart(RequestInterface $request): array
    {
        [$userId, $sessionId] = $this->identity();
        $cart = $this->carts->add(
            $userId,
            $sessionId,
            productId: (string) $request->input('product_id', ''),
            quantity: (int) $request->input('quantity', 1),
            variantId: $request->input('variant_id') !== null ? (string) $request->input('variant_id') : null,
        );

        return $this->cartArray($cart);
    }

    /** PUT /api/v1/shop/cart/items/{id} */
    public function updateCartItem(RequestInterface $request, string $id): array
    {
        [$userId, $sessionId] = $this->identity();
        $cart = $this->carts->updateQuantity($userId, $sessionId, $id, (int) $request->input('quantity', 1));

        return $this->cartArray($cart);
    }

    /** DELETE /api/v1/shop/cart/items/{id} */
    public function removeCartItem(RequestInterface $request, string $id): array
    {
        [$userId, $sessionId] = $this->identity();
        $cart = $this->carts->remove($userId, $sessionId, $id);

        return $this->cartArray($cart);
    }

    /** POST /api/v1/shop/checkout */
    public function checkout(RequestInterface $request): array
    {
        [$userId, $sessionId] = $this->identity();
        if ($userId === null) {
            throw new ForbiddenException(messageId: 'shop::messages.login_required');
        }

        $cart = $this->carts->current($userId, $sessionId);
        $order = $this->placeOrder->execute(
            customerId: $userId,
            cart: $cart,
            addressData: [
                'receiver' => (string) $request->input('receiver', ''),
                'phone' => (string) $request->input('phone', ''),
                'province' => (string) $request->input('province', ''),
                'city' => (string) $request->input('city', ''),
                'address' => (string) $request->input('address', ''),
                'postal_code' => (string) $request->input('postal_code', ''),
            ],
            paymentMethod: (string) $request->input('payment_method', 'gateway'),
        );

        return [
            'data' => [
                'order_id' => $order->id(),
                'order_number' => $order->orderNumber(),
                'status' => $order->status()->value,
                'subtotal' => $order->subtotal()->minor,
                'discount' => $order->discount()->minor,
                'tax' => $order->tax()->minor,
                'shipping' => $order->shippingCost()->minor,
                'total' => $order->total()->minor,
                'currency' => $order->currency(),
                'pay_url' => '/shop/pay/' . $order->orderNumber(),
            ],
        ];
    }

    /** GET /api/v1/shop/orders */
    public function orders(RequestInterface $request): array
    {
        $userId = $this->requireUser();
        $page = \max(1, (int) $request->input('page', 1));
        $result = $this->orders->paginate(['customer_id' => $userId], $page, 20);

        return [
            'data' => \array_map(fn ($order): array => $this->orderArray($order, full: false), $result['rows']),
            'meta' => ['total' => $result['total'], 'page' => $page],
        ];
    }

    /** GET /api/v1/shop/orders/{id} */
    public function order(RequestInterface $request, string $id): array
    {
        $userId = $this->requireUser();
        $order = $this->orders->findById($id);
        if ($order === null) {
            throw new RouteNotFoundException(messageId: 'shop::messages.order_not_found');
        }
        if ($order->customerId() !== $userId) {
            throw new ForbiddenException(messageId: 'shop::messages.order_forbidden');
        }

        return ['data' => $this->orderArray($order, full: true)];
    }

    /** POST /api/v1/shop/orders/{id}/cancel */
    public function cancelOrder(RequestInterface $request, string $id): array
    {
        $userId = $this->requireUser();
        $order = $this->orders->findById($id);
        if ($order === null) {
            throw new RouteNotFoundException(messageId: 'shop::messages.order_not_found');
        }
        if ($order->customerId() !== $userId) {
            throw new ForbiddenException(messageId: 'shop::messages.order_forbidden');
        }
        if (!$order->isCancelableByCustomer()) {
            throw new ValidationException(messageId: 'shop::messages.order_not_cancelable');
        }

        $workflow = \app()->make(\Modules\Shop\Application\UseCases\OrderWorkflowService::class);
        $workflow->cancel($order, 'لغو از API');

        return ['data' => ['status' => 'canceled']];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @return array{0: int|null, 1: string|null} */
    private function identity(): array
    {
        $user = Auth::user();
        $userId = $user !== null ? (int) $user->getAuthIdentifier() : null;

        $sessionId = null;
        try {
            $id = Session::id();
            if (\is_string($id) && $id !== '') {
                $sessionId = $id;
            }
        } catch (\Throwable) {
            $sessionId = null;
        }
        if ($userId === null && $sessionId === null) {
            $sessionId = 'guest-anon';
        }

        return [$userId, $sessionId];
    }

    private function requireUser(): int
    {
        $user = Auth::user();
        if ($user === null) {
            throw new ForbiddenException(messageId: 'shop::messages.login_required');
        }

        return (int) $user->getAuthIdentifier();
    }

    /** @return array<string, mixed> */
    private function productArray(Product $p): array
    {
        return [
            'id' => $p->id(),
            'title' => $p->title(),
            'slug' => $p->slug(),
            'summary' => (string) $p->summary(),
            'category_id' => $p->categoryId(),
            'price' => $p->price()->minor,
            'sale_price' => $p->salePrice()?->minor,
            'effective_price' => $p->effectivePrice()->minor,
            'currency' => $p->currency(),
            'sku' => $p->sku(),
            'stock' => $p->stock(),
            'in_stock' => !$p->isOutOfStock(),
            'is_featured' => $p->isFeatured(),
            'image' => $p->featuredImage(),
            'url' => '/shop/products/' . $p->slug(),
        ];
    }

    /** @return array<string, mixed> */
    private function cartArray(\Modules\Shop\Domain\Entities\Cart $cart): array
    {
        $summary = $this->carts->summary($cart);

        return [
            'data' => [
                'items' => \array_map(static fn ($item): array => [
                    'id' => $item->id(),
                    'product_id' => $item->productId(),
                    'variant_id' => $item->variantId(),
                    'title' => $item->title(),
                    'quantity' => $item->quantity(),
                    'unit_price' => $item->unitPrice()->minor,
                    'line_total' => $item->lineTotal()->minor,
                ], $cart->items()),
                'coupon_code' => $cart->couponCode(),
                'count' => $cart->count(),
            ],
            'summary' => [
                'subtotal' => $summary['subtotal']->minor,
                'discount' => $summary['discount']->minor,
                'shipping' => $summary['shipping']->minor,
                'tax' => $summary['tax']->minor,
                'total' => $summary['total']->minor,
                'currency' => $summary['subtotal']->currency,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function orderArray(\Modules\Shop\Domain\Entities\Order $order, bool $full): array
    {
        $data = [
            'id' => $order->id(),
            'order_number' => $order->orderNumber(),
            'status' => $order->status()->value,
            'status_fa' => $order->status()->labelFa(),
            'subtotal' => $order->subtotal()->minor,
            'discount' => $order->discount()->minor,
            'tax' => $order->tax()->minor,
            'shipping' => $order->shippingCost()->minor,
            'total' => $order->total()->minor,
            'currency' => $order->currency(),
            'placed_at' => $order->placedAt()?->format('c'),
            'paid_at' => $order->paidAt()?->format('c'),
            'cancellable' => $order->isCancelableByCustomer(),
        ];

        if ($full) {
            $data['items'] = \array_map(static fn ($item): array => [
                'title' => $item->title,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unitPrice()->minor,
                'total' => $item->lineTotal()->minor,
            ], $order->items());
            $data['shipping_address'] = $order->shippingAddress()?->toArray();
            $data['tracking_code'] = $order->trackingCode();
        }

        return $data;
    }
}

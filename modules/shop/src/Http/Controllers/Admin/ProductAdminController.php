<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ProductAdminController — مدیریت کاتالوگ در پنل (/admin/shop/products).
 */

namespace Modules\Shop\Http\Controllers\Admin;

use Modules\Media\Contracts\MediaGatewayInterface;
use Modules\Shop\Application\UseCases\AdminProductService;
use Modules\Shop\Domain\Repositories\ProductRepositoryInterface;
use Modules\Shop\Domain\Repositories\ShopCategoryRepositoryInterface;
use Modules\Shop\Domain\Services\InventoryService;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\Validator;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class ProductAdminController
{
    public function __construct(
        private readonly AdminProductService $service,
        private readonly ProductRepositoryInterface $products,
        private readonly ShopCategoryRepositoryInterface $categories,
        private readonly MediaGatewayInterface $mediaGateway,
        private readonly InventoryService $inventory,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('shop.product.view');

        $page = \max(1, (int) $request->input('page', 1));
        $result = $this->products->paginate([
            'search' => (string) $request->input('q', ''),
            'status' => $request->input('status') !== null ? (string) $request->input('status') : null,
            'sort' => (string) $request->input('sort', 'newest'),
        ], $page, 20);

        return View::render('shop::admin.products-index', [
            'products' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'query' => (string) $request->input('q', ''),
            'statusFilter' => (string) $request->input('status', ''),
            'categories' => $this->categories->all(),
        ]);
    }

    public function create(): mixed
    {
        $this->authorize('shop.product.create');

        return View::render('shop::admin.product-form', [
            'product' => null,
            'variants' => [],
            'categories' => $this->categories->all(),
        ]);
    }

    public function store(RequestInterface $request): mixed
    {
        $this->authorize('shop.product.create');

        try {
            $product = $this->service->store($this->productInput($request));
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        if (($status = (string) $request->input('status', '')) !== '' && $status !== 'draft') {
            $this->service->setStatus($product, $status);
        }

        Session::flash('status', 'product_created');



        return redirect('/admin/shop/products/' . $product->id() . '/edit');
    }

    public function edit(string $id): mixed
    {
        $this->authorize('shop.product.view');

        $product = $this->findOr404($id);
        $this->authorizeModel('update', $product);

        return View::render('shop::admin.product-form', [
            'product' => $product,
            'variants' => $this->products->variantsOf($product->id()),
            'categories' => $this->categories->all(),
        ]);
    }

    public function update(RequestInterface $request, string $id): mixed
    {
        $product = $this->findOr404($id);
        $this->authorizeModel('update', $product);

        try {
            $product = $this->service->update($product, $this->productInput($request));
            if (($status = (string) $request->input('status', '')) !== '' && $status !== $product->status()->value) {
                $product = $this->service->setStatus($product, $status);
            }
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'product_updated');



        return redirect('/admin/shop/products/' . $product->id() . '/edit');
    }

    public function destroy(string $id): mixed
    {
        $product = $this->findOr404($id);
        $this->authorizeModel('delete', $product);

        $this->service->softDelete($product);

        Session::flash('status', 'product_deleted');



        return redirect('/admin/shop/products');
    }

    /** بارگذاری تصویر محصول — POST /admin/shop/products/{id}/media */
    public function uploadMedia(RequestInterface $request, string $id): mixed
    {
        $product = $this->findOr404($id);
        $this->authorizeModel('update', $product);

        $validated = Validator::validate($request->files(), [
            'image' => ['required', 'uploaded_file', 'max:8192'],
        ], attributes: ['image' => 'تصویر محصول']);

        $ulid = $this->mediaGateway->store(
            $validated['image'],
            folder: 'shop',
            subjectType: $product->mediaSubjectType(),
            subjectId: $product->mediaSubjectId(),
            disk: $this->mediaGateway->diskFor('shop', null),
        );

        Session::flash('status', 'media_uploaded')->with('media_id', $ulid);



        return back();
    }

    /** افزودن تنوع — POST /admin/shop/products/{id}/variants */
    public function addVariant(RequestInterface $request, string $id): mixed
    {
        $product = $this->findOr404($id);
        $this->authorizeModel('update', $product);

        try {
            $this->service->addVariant($product, $request->all());
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'variant_added');



        return back();
    }

    /** حذف تنوع — DELETE /admin/shop/products/{id}/variants/{variantId} */
    public function removeVariant(string $id, string $variantId): mixed
    {
        $product = $this->findOr404($id);
        $this->authorizeModel('update', $product);

        $this->service->removeVariant($product, $variantId);

        Session::flash('status', 'variant_removed');



        return back();
    }

    /** تنظیم موجودی (inventory-keeper) — POST /admin/shop/products/{id}/inventory */
    public function adjustInventory(RequestInterface $request, string $id): mixed
    {
        $product = $this->findOr404($id);
        if (!Gate::allows('shop.inventory.manage') && !Gate::allows('update', $product)) {
            throw new ForbiddenException(messageId: 'shop::messages.forbidden');
        }

        $validated = Validator::validate($request->all(), [
            'delta' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ], attributes: ['delta' => 'تغییر موجودی']);

        // از راه سرویس (با ثبت movement در shop_stock_movements)
        $this->inventory->adjust($product->id(), null, (int) $validated['delta'], (string) ($validated['note'] ?? ''));

        Session::flash('status', 'inventory_adjusted');



        return back();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function productInput(RequestInterface $request): array
    {
        return [
            'title' => (string) $request->input('title', ''),
            'summary' => (string) $request->input('summary', ''),
            'description' => (string) $request->input('description', ''),
            'category_id' => $request->input('category_id'),
            'price' => $request->input('price'),
            'sale_price' => $request->input('sale_price', ''),
            'sku' => (string) $request->input('sku', ''),
            'stock' => $request->input('stock', 0),
            'featured_image' => (string) $request->input('featured_image', ''),
            'weight' => $request->input('weight', ''),
            'is_featured' => $request->input('is_featured') !== null,
            'low_stock_threshold' => $request->input('low_stock_threshold', 5),
            'attributes' => (string) $request->input('attributes', ''),
            'status' => (string) $request->input('status', 'draft'),
        ];
    }

    private function findOr404(string $id): \Modules\Shop\Domain\Entities\Product
    {
        $product = $this->products->findById($id);
        if ($product === null) {
            throw new RouteNotFoundException(messageId: 'shop::messages.product_not_found');
        }

        return $product;
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'shop::messages.forbidden', context: ['permission' => $permission]);
        }
    }

    private function authorizeModel(string $ability, object $model): void
    {
        if (!Gate::allows($ability, $model)) {
            throw new ForbiddenException(messageId: 'shop::messages.forbidden', context: ['ability' => $ability]);
        }
    }
}

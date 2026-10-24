<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CategoryAdminController — مدیریت دسته‌های فروشگاه (/admin/shop/categories).
 */

namespace Modules\Shop\Http\Controllers\Admin;

use Modules\Shop\Application\UseCases\AdminProductService;
use Modules\Shop\Domain\Repositories\ShopCategoryRepositoryInterface;
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
final class CategoryAdminController
{
    public function __construct(
        private readonly ShopCategoryRepositoryInterface $categories,
        private readonly AdminProductService $service,
    ) {
    }

    public function index(): mixed
    {
        $this->authorize();

        return View::render('shop::admin.categories-index', [
            'categories' => $this->categories->all(),
        ]);
    }

    public function store(RequestInterface $request): mixed
    {
        $this->authorize();

        $validated = Validator::validate($request->all(), [
            'title' => ['required', 'string', 'max:190'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['title' => 'عنوان دسته']);

        $this->service->storeCategory(
            title: (string) $validated['title'],
            parentId: isset($validated['parent_id']) && $validated['parent_id'] !== null && $validated['parent_id'] !== ''
                ? (int) $validated['parent_id']
                : null,
            description: isset($validated['description']) ? (string) $validated['description'] : null,
        );

        Session::flash('status', 'category_created');



        return redirect('/admin/shop/categories');
    }

    public function destroy(string $id): mixed
    {
        $this->authorize();

        $category = $this->categories->findById((int) $id);
        if ($category === null) {
            throw new RouteNotFoundException(messageId: 'shop::messages.category_not_found');
        }

        $this->categories->delete((int) $id);

        Session::flash('status', 'category_deleted');



        return redirect('/admin/shop/categories');
    }

    private function authorize(): void
    {
        if (!Gate::allows('shop.product.update') && !Gate::allows('shop.product.create')) {
            throw new ForbiddenException(messageId: 'shop::messages.forbidden');
        }
    }
}

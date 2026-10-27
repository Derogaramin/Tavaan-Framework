<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqCategoryAdminController — مدیریت دسته‌ها (/admin/faq-categories).
 */

namespace Modules\Faq\Http\Controllers\Admin;

use Modules\Faq\Application\UseCases\FaqWriteService;
use Modules\Faq\Domain\Entities\FaqCategory;
use Modules\Faq\Domain\Repositories\FaqCategoryRepositoryInterface;
use Modules\Faq\Domain\Repositories\FaqItemRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * FaqCategoryAdminController — فهرست + ایجاد/ویرایش/حذف دسته‌ها با Gate (faq.*).
 *
 * حذف دسته نرم است؛ پرسش‌هایش در نمای عمومی «بدون دسته» نمایش داده می‌شوند.
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
final class FaqCategoryAdminController
{
    public function __construct(
        private readonly FaqCategoryRepositoryInterface $categories,
        private readonly FaqItemRepositoryInterface $items,
        private readonly FaqWriteService $write,
    ) {
    }

    /** GET /admin/faq-categories */
    public function index(): mixed
    {
        Gate::authorize('faq.view');

        $categories = $this->categories->all();
        $counts = [];
        foreach ($categories as $category) {
            $counts[$category->id()] = $this->items->countByCategory($category->id());
        }

        return view('faq::admin.categories-index', [
            'categories' => $categories,
            'counts' => $counts,
            'ok' => Session::pull('faq_cat_ok'),
            'errors' => Session::pull('faq_cat_errors') ?? [],
            'old' => Session::pull('faq_cat_old') ?? [],
        ]);
    }

    /** POST /admin/faq-categories */
    public function store(RequestInterface $request): mixed
    {
        Gate::authorize('faq.create');

        $input = $this->categoryInput($request);

        try {
            $this->write->createCategory($input);
            Session::flash('faq_cat_ok', trans('faq::messages.category_created'));
        } catch (ValidationException $e) {
            Session::flash('faq_cat_errors', $e->errors);
            Session::flash('faq_cat_old', $input);
        }

        return redirect('/admin/faq-categories');
    }

    /** POST /admin/faq-categories/{id} */
    public function update(string $id, RequestInterface $request): mixed
    {
        Gate::authorize('faq.update');
        $category = $this->findOr404($id);

        $input = $this->categoryInput($request);

        try {
            $this->write->updateCategory($category, $input);
            Session::flash('faq_cat_ok', trans('faq::messages.category_updated'));
        } catch (ValidationException $e) {
            Session::flash('faq_cat_errors', $e->errors);
        }

        return redirect('/admin/faq-categories');
    }

    /** DELETE /admin/faq-categories/{id} — حذف نرم */
    public function destroy(string $id): mixed
    {
        Gate::authorize('faq.delete');
        $category = $this->findOr404($id);

        $this->write->trashCategory($category);
        Session::flash('faq_cat_ok', trans('faq::messages.category_deleted'));

        return redirect('/admin/faq-categories');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id): FaqCategory
    {
        $category = $this->categories->findById($id);
        if ($category === null || $category->isTrashed()) {
            throw new RouteNotFoundException(messageId: 'faq::messages.category_not_found', context: ['id' => $id]);
        }

        return $category;
    }

    /** @return array<string, mixed> */
    private function categoryInput(RequestInterface $request): array
    {
        return [
            'title' => (string) $request->input('title', ''),
            'slug' => (string) $request->input('slug', ''),
            'sort_order' => (string) $request->input('sort_order', ''),
        ];
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * CategoryAdminController — مدیریت دسته‌های خبری (DOC-092 §4: chief-editor).
 */

namespace Modules\News\Http\Controllers\Admin;

use Modules\Content\Domain\ValueObjects\Slug;
use Modules\News\Domain\Entities\NewsCategory;
use Modules\News\Domain\Repositories\NewsCategoryRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * @api 1.0
 * @see modules/news/README.md
 */
final class CategoryAdminController
{
    public function __construct(
        private readonly NewsCategoryRepositoryInterface $categories,
    ) {
    }

    /** GET /admin/news/categories */
    public function index(): mixed
    {
        Gate::authorize('news.category.view');

        return view('news::admin.categories-index', [
            'categories' => $this->categories->all(),
        ]);
    }

    /** POST /admin/news/categories */
    public function store(RequestInterface $request): mixed
    {
        Gate::authorize('news.category.manage');

        $title = \trim((string) ($request->input('title') ?? ''));
        if ($title === '') {
            Session::flash('news_category_error', trans('news::messages.category_title_required'));

            return redirect('/admin/news/categories');
        }

        $slugInput = \trim((string) ($request->input('slug') ?? ''));
        $slug = $slugInput !== '' ? Slug::slugify($slugInput) : Slug::slugify($title);
        if ($slug === '') {
            $slug = 'category';
        }
        $slug = Slug::unique($slug, fn (string $c): bool => $this->categories->slugExists($c))->toString();

        $parentRaw = $request->input('parent_id');
        $parentId = null;
        if ($parentRaw !== null && $parentRaw !== '' && $this->categories->findById((int) $parentRaw) !== null) {
            $parentId = (int) $parentRaw;
        }

        $description = $request->input('description');
        $this->categories->save(NewsCategory::create(
            $title,
            $slug,
            $parentId,
            \is_string($description) && $description !== '' ? $description : null,
        ));

        Session::flash('news_category_ok', trans('news::messages.category_created'));

        return redirect('/admin/news/categories');
    }

    /** POST /admin/news/categories/{id} */
    public function update(RequestInterface $request, string $id): mixed
    {
        Gate::authorize('news.category.manage');

        $category = $this->categories->findById((int) $id);
        if ($category === null) {
            throw new RouteNotFoundException(messageId: 'news::messages.category_not_found');
        }

        $title = \trim((string) ($request->input('title') ?? ''));
        if ($title === '') {
            Session::flash('news_category_error', trans('news::messages.category_title_required'));

            return redirect('/admin/news/categories');
        }

        $parentRaw = $request->input('parent_id');
        $parentId = null;
        if ($parentRaw !== null && $parentRaw !== '' && (int) $parentRaw !== $category->id()
            && $this->categories->findById((int) $parentRaw) !== null) {
            $parentId = (int) $parentRaw;
        }

        $description = $request->input('description');
        $category->edit($title, $parentId, \is_string($description) ? $description : null);
        $this->categories->save($category);

        Session::flash('news_category_ok', trans('news::messages.category_updated'));

        return redirect('/admin/news/categories');
    }

    /** DELETE /admin/news/categories/{id} */
    public function destroy(string $id): mixed
    {
        Gate::authorize('news.category.manage');

        if ($this->categories->findById((int) $id) === null) {
            throw new RouteNotFoundException(messageId: 'news::messages.category_not_found');
        }

        $this->categories->delete((int) $id);
        Session::flash('news_category_ok', trans('news::messages.category_deleted'));

        return redirect('/admin/news/categories');
    }
}

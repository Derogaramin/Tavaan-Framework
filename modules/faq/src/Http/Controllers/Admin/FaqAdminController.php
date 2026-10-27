<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqAdminController — مدیریت پرسش‌ها (/admin/faqs).
 */

namespace Modules\Faq\Http\Controllers\Admin;

use Modules\Faq\Application\UseCases\FaqWriteService;
use Modules\Faq\Domain\Entities\FaqItem;
use Modules\Faq\Domain\Repositories\FaqCategoryRepositoryInterface;
use Modules\Faq\Domain\Repositories\FaqItemRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * FaqAdminController — CRUD + انتشار/بایگانی با Gate (faq.*).
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
final class FaqAdminController
{
    public function __construct(
        private readonly FaqItemRepositoryInterface $items,
        private readonly FaqCategoryRepositoryInterface $categories,
        private readonly FaqWriteService $write,
    ) {
    }

    /** GET /admin/faqs */
    public function index(RequestInterface $request): mixed
    {
        Gate::authorize('faq.view');

        $page = \max(1, (int) $request->query('page', 1));
        $status = (string) $request->query('status', '');
        $categoryId = (string) $request->query('category_id', '');
        $q = \trim((string) $request->query('q', ''));
        $deleted = (string) $request->query('deleted', '') === '1';
        $perPage = (int) config('faq.admin_per_page', 15);

        $filters = ['with_trashed' => $deleted, 'only_trashed' => $deleted];
        if ($status !== '' && \in_array($status, ['draft', 'published', 'archived'], true)) {
            $filters['status'] = $status;
        }
        if ($categoryId !== '') {
            $filters['category_id'] = $categoryId;
        }
        if ($q !== '') {
            $filters['q'] = $q;
        }

        $result = $this->items->paginate($filters, $page, $perPage);

        $categoryTitles = [];
        foreach ($this->categories->all() as $category) {
            $categoryTitles[$category->id()] = $category->title();
        }

        return view('faq::admin.faqs-index', [
            'items' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'status' => $status,
            'categoryId' => $categoryId,
            'q' => $q,
            'deleted' => $deleted,
            'categories' => $this->categories->all(),
            'categoryTitles' => $categoryTitles,
            'ok' => Session::pull('faq_admin_ok'),
            'errors' => Session::pull('faq_admin_errors') ?? [],
        ]);
    }

    /** GET /admin/faqs/create */
    public function create(): mixed
    {
        Gate::authorize('faq.create');

        return view('faq::admin.faq-form', [
            'item' => null,
            'categories' => $this->categories->all(),
            'errors' => Session::pull('faq_admin_errors') ?? [],
            'old' => Session::pull('faq_admin_old') ?? [],
        ]);
    }

    /** POST /admin/faqs */
    public function store(RequestInterface $request): mixed
    {
        Gate::authorize('faq.create');

        $input = $this->itemInput($request);

        try {
            $item = $this->write->create($input, (int) Auth::id());
        } catch (ValidationException $e) {
            Session::flash('faq_admin_errors', $e->errors);
            Session::flash('faq_admin_old', $input);

            return redirect('/admin/faqs/create');
        }

        Session::flash('faq_admin_ok', trans('faq::messages.faq_created'));

        return redirect('/admin/faqs/' . $item->id() . '/edit');
    }

    /** GET /admin/faqs/{id}/edit */
    public function edit(string $id): mixed
    {
        Gate::authorize('faq.update');
        $item = $this->findOr404($id);

        return view('faq::admin.faq-form', [
            'item' => $item,
            'categories' => $this->categories->all(),
            'errors' => Session::pull('faq_admin_errors') ?? [],
            'old' => Session::pull('faq_admin_old') ?? [],
            'ok' => Session::pull('faq_admin_ok'),
        ]);
    }

    /** POST /admin/faqs/{id} */
    public function update(string $id, RequestInterface $request): mixed
    {
        Gate::authorize('faq.update');
        $item = $this->findOr404($id);
        $input = $this->itemInput($request);

        try {
            $this->write->update($item, $input);
        } catch (ValidationException $e) {
            Session::flash('faq_admin_errors', $e->errors);
            Session::flash('faq_admin_old', $input);

            return redirect('/admin/faqs/' . $item->id() . '/edit');
        }

        Session::flash('faq_admin_ok', trans('faq::messages.faq_updated'));

        return redirect('/admin/faqs/' . $item->id() . '/edit');
    }

    /** POST /admin/faqs/{id}/publish */
    public function publish(string $id): mixed
    {
        Gate::authorize('faq.publish');
        $item = $this->findOr404($id);

        try {
            $this->write->publish($item);
            Session::flash('faq_admin_ok', trans('faq::messages.faq_published'));
        } catch (\DomainException $e) {
            Session::flash('faq_admin_errors', ['status' => [$e->getMessage()]]);
        }

        return redirect('/admin/faqs/' . $item->id() . '/edit');
    }

    /** POST /admin/faqs/{id}/archive */
    public function archive(string $id): mixed
    {
        Gate::authorize('faq.publish');
        $item = $this->findOr404($id);

        try {
            $this->write->archive($item);
            Session::flash('faq_admin_ok', trans('faq::messages.faq_archived'));
        } catch (\DomainException $e) {
            Session::flash('faq_admin_errors', ['status' => [$e->getMessage()]]);
        }

        return redirect('/admin/faqs/' . $item->id() . '/edit');
    }

    /** POST /admin/faqs/{id}/draft — بازگردانی به پیش‌نویس (از published/archived) */
    public function draft(string $id): mixed
    {
        Gate::authorize('faq.publish');
        $item = $this->findOr404($id);

        try {
            $this->write->backToDraft($item);
            Session::flash('faq_admin_ok', trans('faq::messages.faq_back_to_draft'));
        } catch (\DomainException $e) {
            Session::flash('faq_admin_errors', ['status' => [$e->getMessage()]]);
        }

        return redirect('/admin/faqs/' . $item->id() . '/edit');
    }

    /** DELETE /admin/faqs/{id} — حذف نرم (و سخت برای trash‌شده‌ها) */
    public function destroy(string $id): mixed
    {
        Gate::authorize('faq.delete');
        $item = $this->findOr404($id);

        if ($item->isTrashed()) {
            $this->write->forceDelete($item);
            Session::flash('faq_admin_ok', trans('faq::messages.faq_deleted_forever'));
        } else {
            $this->write->trash($item);
            Session::flash('faq_admin_ok', trans('faq::messages.faq_trashed'));
        }

        return redirect('/admin/faqs');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id): FaqItem
    {
        $item = $this->items->findById($id);
        if ($item === null) {
            throw new RouteNotFoundException(messageId: 'faq::messages.item_not_found', context: ['id' => $id]);
        }

        return $item;
    }

    /** @return array<string, mixed> */
    private function itemInput(RequestInterface $request): array
    {
        return [
            'question' => (string) $request->input('question', ''),
            'answer' => (string) $request->input('answer', ''),
            'category_id' => (string) $request->input('category_id', ''),
            'sort_order' => (string) $request->input('sort_order', ''),
            'status' => (string) $request->input('status', 'draft'),
        ];
    }
}

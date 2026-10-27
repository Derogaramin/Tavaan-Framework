<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * PageAdminController — مدیریت صفحات CMS (/admin/pages).
 */

namespace Modules\Content\Http\Controllers\Admin;

use Modules\Content\Application\UseCases\PageWriteService;
use Modules\Content\Domain\Entities\Page;
use Modules\Content\Domain\Repositories\PageRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * PageAdminController — CRUD صفحات با Gate/Policy (content.page.*).
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class PageAdminController
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly PageWriteService $write,
    ) {
    }

    /** GET /admin/pages */
    public function index(RequestInterface $request): mixed
    {
        Gate::authorize('content.page.view');

        $page = \max(1, (int) $request->query('page', 1));
        $status = (string) $request->query('status', '');
        $perPage = 15;

        $filters = ['with_trashed' => false];
        if ($status !== '' && \in_array($status, ['draft', 'published', 'archived'], true)) {
            $filters['status'] = $status;
        }

        $result = $this->pages->paginate($filters, $page, $perPage);

        return view('content::admin.pages-index', [
            'pages' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'status' => $status,
            'ok' => Session::pull('content_page_ok'),
        ]);
    }

    /** GET /admin/pages/create */
    public function create(): mixed
    {
        Gate::authorize('content.page.create');

        return view('content::admin.page-form', [
            'page' => null,
            'errors' => Session::pull('content_page_errors') ?? [],
            'old' => Session::pull('content_page_old') ?? [],
        ]);
    }

    /** POST /admin/pages */
    public function store(RequestInterface $request): mixed
    {
        Gate::authorize('content.page.create');

        $input = $this->pageInput($request);

        try {
            $page = $this->write->create($input, (int) Auth::id());
        } catch (ValidationException $e) {
            Session::flash('content_page_errors', $e->errors);
            Session::flash('content_page_old', $input);

            return redirect('/admin/pages/create');
        }

        Session::flash('content_page_ok', trans('content::messages.page_created'));

        return redirect('/admin/pages/' . $page->id() . '/edit');
    }

    /** GET /admin/pages/{id}/edit */
    public function edit(string $id): mixed
    {
        Gate::authorize('content.page.update');
        $page = $this->findOr404($id);

        return view('content::admin.page-form', [
            'page' => $page,
            'errors' => Session::pull('content_page_errors') ?? [],
            'old' => Session::pull('content_page_old') ?? [],
            'ok' => Session::pull('content_page_ok'),
        ]);
    }

    /** POST /admin/pages/{id} */
    public function update(string $id, RequestInterface $request): mixed
    {
        Gate::authorize('content.page.update');
        $page = $this->findOr404($id);
        $input = $this->pageInput($request);

        try {
            $this->write->update($page, $input);
        } catch (ValidationException $e) {
            Session::flash('content_page_errors', $e->errors);
            Session::flash('content_page_old', $input);

            return redirect('/admin/pages/' . $page->id() . '/edit');
        }

        Session::flash('content_page_ok', trans('content::messages.page_updated'));

        return redirect('/admin/pages/' . $page->id() . '/edit');
    }

    /** POST /admin/pages/{id}/publish */
    public function publish(string $id): mixed
    {
        Gate::authorize('content.page.publish');
        $page = $this->findOr404($id);

        try {
            $this->write->publish($page);
            Session::flash('content_page_ok', trans('content::messages.page_published'));
        } catch (\DomainException $e) {
            Session::flash('content_page_errors', ['status' => [$e->getMessage()]]);
        }

        return redirect('/admin/pages/' . $page->id() . '/edit');
    }

    /** POST /admin/pages/{id}/archive */
    public function archive(string $id): mixed
    {
        Gate::authorize('content.page.publish');
        $page = $this->findOr404($id);

        try {
            $this->write->archive($page);
            Session::flash('content_page_ok', trans('content::messages.page_archived'));
        } catch (\DomainException $e) {
            Session::flash('content_page_errors', ['status' => [$e->getMessage()]]);
        }

        return redirect('/admin/pages/' . $page->id() . '/edit');
    }

    /** DELETE /admin/pages/{id} — حذف نرم (trash) */
    public function destroy(string $id): mixed
    {
        Gate::authorize('content.page.delete');
        $page = $this->findOr404($id, withTrashed: false);

        $this->write->trash($page);
        Session::flash('content_page_ok', trans('content::messages.page_trashed'));

        return redirect('/admin/pages');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id, bool $withTrashed = true): Page
    {
        $page = $this->pages->findById($id);

        if ($page === null || (!$withTrashed && $page->isTrashed())) {
            throw new RouteNotFoundException(messageId: 'content::messages.page_not_found', context: ['id' => $id]);
        }

        return $page;
    }

    /** @return array<string, mixed> */
    private function pageInput(RequestInterface $request): array
    {
        return [
            'title' => (string) $request->input('title', ''),
            'slug' => (string) $request->input('slug', ''),
            'body' => (string) $request->input('body', ''),
            'excerpt' => (string) $request->input('excerpt', ''),
            'seo_title' => (string) $request->input('seo_title', ''),
            'seo_description' => (string) $request->input('seo_description', ''),
            'seo_keywords' => (string) $request->input('seo_keywords', ''),
            'comments_open' => (bool) $request->input('comments_open', false),
            'status' => (string) $request->input('status', 'draft'),
        ];
    }
}

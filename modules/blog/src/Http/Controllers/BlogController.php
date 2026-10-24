<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * BlogController — صفحات عمومی وبلاگ (DOC-091 §3).
 */

namespace Modules\Blog\Http\Controllers;

use Modules\Blog\Application\UseCases\PostReadService;
use Modules\Blog\Domain\Repositories\CategoryRepositoryInterface;
use Modules\Blog\Domain\Repositories\TagRepositoryInterface;
use Modules\Content\Domain\Services\JalaliMonth;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Session;

/**
 * BlogController — فهرست/دسته/برچسب/آرشیو جلالی/نمایش مقاله.
 *
 * خروجی: View (namespace `blog::`) — توسط ControllerResolver رندر می‌شود.
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class BlogController
{
    public function __construct(
        private readonly PostReadService $read,
        private readonly CategoryRepositoryInterface $categories,
        private readonly TagRepositoryInterface $tags,
    ) {
    }

    /** GET /blog — فهرست مقالات (paginate) + جست‌وجو با ?q= */
    public function index(RequestInterface $request): mixed
    {
        $page = $this->page($request);
        $query = \trim((string) $request->query('q', ''));

        $result = $query !== ''
            ? $this->read->search($query, $page)
            : $this->read->listPublished([], $page);

        return view('blog::index', [
            'result' => $result,
            'categories' => $this->categories->all(),
            'query' => $query,
            'heading' => $query !== ''
                ? trans('blog::messages.search_results', ['q' => $query])
                : trans('blog::messages.latest_posts'),
        ]);
    }

    /** GET /blog/category/{slug} */
    public function category(RequestInterface $request, string $slug): mixed
    {
        $category = $this->categories->findBySlug($slug);
        if ($category === null || $category->id() === null) {
            throw RouteNotFoundException::forPath('GET', '/blog/category/' . $slug);
        }

        $result = $this->read->listPublished(['category_id' => $category->id()], $this->page($request));

        return view('blog::index', [
            'result' => $result,
            'categories' => $this->categories->all(),
            'query' => '',
            'heading' => $category->name(),
            'headingDescription' => $category->description(),
        ]);
    }

    /** GET /blog/tag/{slug} */
    public function tag(RequestInterface $request, string $slug): mixed
    {
        $tag = $this->tags->findBySlug($slug);
        if ($tag === null || $tag->id() === null) {
            throw RouteNotFoundException::forPath('GET', '/blog/tag/' . $slug);
        }

        $result = $this->read->listPublished(['tag_id' => $tag->id()], $this->page($request));

        return view('blog::index', [
            'result' => $result,
            'categories' => $this->categories->all(),
            'query' => '',
            'heading' => trans('blog::messages.tag_heading', ['tag' => $tag->name()]),
        ]);
    }

    /** GET /blog/archive/{year}/{month} — آرشیو جلالی (E2E-05) */
    public function archive(RequestInterface $request, string $year, string $month): mixed
    {
        try {
            $jalali = JalaliMonth::fromRoute($year, $month);
        } catch (\OutOfRangeException) {
            throw RouteNotFoundException::forPath('GET', '/blog/archive/' . $year . '/' . $month);
        }

        $result = $this->read->archive($jalali->year, $jalali->month, $this->page($request));

        return view('blog::index', [
            'result' => $result,
            'categories' => $this->categories->all(),
            'query' => '',
            'heading' => trans('blog::messages.archive_heading', ['month' => $jalali->labelFa()]),
        ]);
    }

    /** GET /blog/{slug} — مقاله + نظرات (DOC-091 §3) */
    public function show(RequestInterface $request, string $slug): mixed
    {
        $post = $this->read->showPublished($slug);

        return view('blog::show', [
            'post' => $post,
            'thread' => $this->read->approvedCommentThread($post),
            'author' => $this->read->authorOf($post),
            'tags' => $this->read->tagsOf($post),
            'categories' => $this->categories->all(),
            'commentsEnabled' => $post->commentsOpen(),
            'flash' => Session::pull('blog_comment'),
        ]);
    }

    private function page(RequestInterface $request): int
    {
        $page = (int) $request->query('page', 1);

        return \max(1, $page);
    }
}

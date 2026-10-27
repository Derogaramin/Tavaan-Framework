<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * PageController — نمایش عمومی صفحات CMS.
 */

namespace Modules\Content\Http\Controllers;

use Modules\Content\Application\UseCases\PageReadService;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Session;

/**
 * PageController — GET /pages + GET /pages/{slug} (+ sitemap صفحات).
 *
 * 🔒 فقط صفحات published و غیر‌trash نمایش داده می‌شوند (PageReadService).
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class PageController
{
    public function __construct(
        private readonly PageReadService $read,
        private readonly ContainerInterface $container,
    ) {
    }

    /** GET /pages — فهرست صفحات منتشرشده */
    public function index(RequestInterface $request): mixed
    {
        $limit = (int) config('content.pages.per_page', 12);

        return view('content::pages-index', [
            'pages' => $this->read->listPublished($limit),
        ]);
    }

    /** GET /pages/{slug} — نمایش صفحه */
    public function show(string $slug, RequestInterface $request): mixed
    {
        $page = $this->read->publishedBySlug($slug);
        if ($page === null) {
            throw new RouteNotFoundException(messageId: 'content::messages.page_not_found', context: ['slug' => $slug]);
        }

        // شمارش بازدید — یک‌بار در هر جلسه برای هر صفحه (ضد تورم آمار)
        $viewKey = 'content_page_viewed_' . $page->id();
        if (!Session::has($viewKey)) {
            Session::put($viewKey, true);
            $this->read->registerView($page);
        }

        return view('content::page-show', [
            'page' => $page,
            'thread' => $this->commentThread($page),
            'canComment' => $this->commentsAvailable() && $page->commentsOpen(),
        ]);
    }

    /** GET /pages-sitemap.xml — sitemap اختصاصی صفحات */
    public function sitemap(RequestInterface $request): mixed
    {
        $siteUrl = (string) config('app.url', '');
        if ($siteUrl === '') {
            $scheme = $request->isSecure() ? 'https' : 'http';
            $siteUrl = $scheme . '://' . (string) ($request->header('host') ?? 'localhost');
        }

        $factory = $this->container->make('response.factory');

        return $factory->make($this->read->sitemap($siteUrl), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * رشتهٔ نظرات تأییدشده از ماژول comment (وابستگی نرم — بدون آن null).
     *
     * @return list<\Modules\Comment\Domain\ValueObjects\ThreadNode>|null
     */
    private function commentThread(\Modules\Content\Domain\Entities\Page $page): ?array
    {
        if (!$this->commentsAvailable() || !$page->commentsOpen()) {
            return null;
        }

        try {
            if (!$this->container->bound(\Modules\Comment\Application\UseCases\SubmitCommentService::class)) {
                return null;
            }

            /** @var \Modules\Comment\Application\UseCases\SubmitCommentService $comments */
            $comments = $this->container->make(\Modules\Comment\Application\UseCases\SubmitCommentService::class);
            $registry = $this->container->make(\Modules\Comment\Application\CommentableRegistry::class);

            if (!$registry->has($page->commentableType())) {
                return null;
            }

            return $comments->approvedThread($page->commentableType(), $page->commentableId());
        } catch (\Throwable) {
            return null;
        }
    }

    private function commentsAvailable(): bool
    {
        return (bool) config('content.pages.comments', true)
            && $this->container->bound(\Modules\Comment\Application\UseCases\SubmitCommentService::class);
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * PageReadService — مسیر خواندن صفحات (عمومی + آمار).
 */

namespace Modules\Content\Application\UseCases;

use Modules\Content\Domain\Entities\Page;
use Modules\Content\Domain\Repositories\PageRepositoryInterface;
use Modules\Content\Domain\Services\SitemapBuilder;
use Modules\Content\Domain\ValueObjects\SitemapEntry;

/**
 * PageReadService — UseCase خواندن صفحات منتشرشده.
 *
 * - دسترسی عمومی فقط به `published` و غیر‌trash (دیتابیس whereStatus)
 * - شمارش بازدید با تفکیک جلسه (session guard در کنترلر)
 * - ورودی‌های sitemap از صفحات منتشرشده
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class PageReadService
{
    /** @param array<string, mixed> $config کلید config('content') */
    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly SitemapBuilder $sitemap,
        private readonly array $config = [],
    ) {
    }

    /** صفحهٔ منتشرشده از slug — در غیر این صورت null (کنترلر 404 می‌دهد). */
    public function publishedBySlug(string $slug): ?Page
    {
        $page = $this->pages->findBySlug($slug);

        return $page !== null && $page->isPublished() ? $page : null;
    }

    /** ثبت بازدید و بازگرداندن شمار تازه. */
    public function registerView(Page $page): int
    {
        $this->pages->incrementViews($page->id());

        return $page->viewCount() + 1;
    }

    /**
     * فهرست عمومی صفحات منتشرشده.
     *
     * @return list<Page>
     */
    public function listPublished(int $limit = 50): array
    {
        return $this->pages->latestPublished($limit);
    }

    /** XML sitemap صفحات منتشرشده (SitemapBuilder دامنه). */
    public function sitemap(string $siteUrl): string
    {
        $entries = [];
        foreach ($this->pages->latestPublished((int) ($this->config['sitemap_limit'] ?? 500)) as $page) {
            $entries[] = new SitemapEntry(
                loc: \rtrim($siteUrl, '/') . '/pages/' . $page->slug(),
                lastmod: $page->updatedAt() ?? $page->publishedAt(),
                changefreq: 'monthly',
                priority: 0.6,
            );
        }

        return $this->sitemap->build($entries);
    }
}

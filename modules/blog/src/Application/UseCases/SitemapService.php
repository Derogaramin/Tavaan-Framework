<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * SitemapService — sitemap.xml مقالات/دسته‌ها (DOC-091 §6: SEO).
 */

namespace Modules\Blog\Application\UseCases;

use Modules\Blog\Domain\Repositories\CategoryRepositoryInterface;
use Modules\Blog\Domain\Repositories\PostRepositoryInterface;
use Modules\Content\Domain\Services\SitemapBuilder;
use Modules\Content\Domain\ValueObjects\SitemapEntry;

/**
 * @api 1.0
 * @see modules/blog/README.md
 */
final class SitemapService
{
    public function __construct(
        private readonly PostRepositoryInterface $posts,
        private readonly CategoryRepositoryInterface $categories,
        private readonly SitemapBuilder $sitemapBuilder,
    ) {
    }

    public function xml(string $siteUrl, int $limit = 1000): string
    {
        $base = \rtrim($siteUrl, '/');
        $entries = [
            new SitemapEntry(loc: $base . '/blog', changefreq: 'daily', priority: 0.9),
        ];

        foreach ($this->categories->all() as $category) {
            $entries[] = new SitemapEntry(
                loc: $base . '/blog/category/' . $category->slug(),
                changefreq: 'weekly',
                priority: 0.6,
            );
        }

        foreach ($this->posts->latestPublished($limit) as $post) {
            $entries[] = new SitemapEntry(
                loc: $base . '/blog/' . $post->slug(),
                lastmod: $post->updatedAt() ?? $post->publishedAt(),
                changefreq: 'monthly',
                priority: 0.8,
            );
        }

        return $this->sitemapBuilder->build($entries);
    }
}

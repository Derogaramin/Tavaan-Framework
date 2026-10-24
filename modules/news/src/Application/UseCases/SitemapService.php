<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * SitemapService — sitemap خودکار اخبار/دسته‌ها (DOC-092 §6).
 */

namespace Modules\News\Application\UseCases;

use Modules\Content\Domain\Services\SitemapBuilder;
use Modules\Content\Domain\ValueObjects\SitemapEntry;
use Modules\News\Domain\Repositories\ArticleRepositoryInterface;
use Modules\News\Domain\Repositories\NewsCategoryRepositoryInterface;

/**
 * @api 1.0
 * @see modules/news/README.md
 */
final class SitemapService
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly NewsCategoryRepositoryInterface $categories,
        private readonly SitemapBuilder $sitemapBuilder,
    ) {
    }

    public function xml(string $siteUrl, int $limit = 2000): string
    {
        $base = \rtrim($siteUrl, '/');
        $entries = [
            new SitemapEntry(loc: $base . '/news', changefreq: 'hourly', priority: 1.0),
        ];

        foreach ($this->categories->all() as $category) {
            $entries[] = new SitemapEntry(
                loc: $base . '/news/category/' . $category->slug(),
                changefreq: 'daily',
                priority: 0.7,
            );
        }

        foreach ($this->articles->latestPublished($limit) as $article) {
            $entries[] = new SitemapEntry(
                loc: $base . '/news/' . $article->slug(),
                lastmod: $article->updatedAt() ?? $article->publishedAt(),
                changefreq: $article->isBreaking() ? 'always' : 'daily',
                priority: $article->isBreaking() ? 1.0 : 0.8,
            );
        }

        return $this->sitemapBuilder->build($entries);
    }
}

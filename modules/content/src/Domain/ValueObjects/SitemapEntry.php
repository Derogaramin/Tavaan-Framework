<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * SitemapEntry — VO یک URL در sitemap.
 */

namespace Modules\Content\Domain\ValueObjects;

/**
 * @api 1.0
 * @see modules/content/README.md
 */
final class SitemapEntry
{
    /**
     * @param string $loc         URL مطلق
     * @param string $changefreq  always|hourly|daily|weekly|monthly|yearly|never
     * @param float  $priority    0.0..1.0
     */
    public function __construct(
        public readonly string $loc,
        public readonly ?\DateTimeImmutable $lastmod = null,
        public readonly string $changefreq = 'weekly',
        public readonly float $priority = 0.5,
    ) {
    }
}

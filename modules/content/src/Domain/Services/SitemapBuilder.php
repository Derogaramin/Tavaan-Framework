<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * SitemapBuilder — تولید sitemap XML (sitemaps.org 0.9) — DOC-091 §6 (SEO).
 */

namespace Modules\Content\Domain\Services;

use Modules\Content\Domain\ValueObjects\SitemapEntry;

/**
 * @api 1.0
 * @see modules/content/README.md
 */
final class SitemapBuilder
{
    private const VALID_CHANGEFREQ = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

    /** @param list<SitemapEntry> $entries */
    public function build(array $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . self::e($entry->loc) . '</loc>' . "\n";
            if ($entry->lastmod !== null) {
                $xml .= '    <lastmod>' . $entry->lastmod->format('Y-m-d\TH:i:sP') . '</lastmod>' . "\n";
            }
            $changefreq = \in_array($entry->changefreq, self::VALID_CHANGEFREQ, true) ? $entry->changefreq : 'weekly';
            $xml .= '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            $priority = \max(0.0, \min(1.0, $entry->priority));
            $xml .= '    <priority>' . \number_format($priority, 1) . '</priority>' . "\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }

    private static function e(string $value): string
    {
        return \htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

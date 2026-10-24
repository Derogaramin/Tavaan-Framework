<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * FeedBuilder — تولید RSS 2.0 و Atom 1.0 معتبر (DOC-091 §3/§6 — AC-BLOG-04).
 */

namespace Modules\Content\Domain\Services;

use Modules\Content\Domain\ValueObjects\FeedItem;
use Modules\Content\Domain\ValueObjects\FeedMeta;

/**
 * FeedBuilder — خروجی XML با escape کامل (ENT_XML1)؛ تاریخ‌ها RFC-822 (RSS)
 * و RFC-3339 (Atom). بدون وابستگی خارجی — قابل تست با simplexml.
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class FeedBuilder
{
    /**
     * ساخت RSS 2.0.
     *
     * @param list<FeedItem> $items
     */
    public function rss(FeedMeta $meta, array $items): string
    {
        $lastBuild = $items !== []
            ? $items[0]->publishedAt->format(\DateTimeInterface::RSS)
            : (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::RSS);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= "  <channel>\n";
        $xml .= '    <title>' . self::e($meta->title) . '</title>' . "\n";
        $xml .= '    <link>' . self::e($meta->siteUrl) . '</link>' . "\n";
        $xml .= '    <description>' . self::e($meta->description) . '</description>' . "\n";
        $xml .= '    <language>' . self::e($meta->language) . '</language>' . "\n";
        $xml .= '    <lastBuildDate>' . $lastBuild . '</lastBuildDate>' . "\n";
        $xml .= '    <atom:link href="' . self::e($meta->feedUrl) . '" rel="self" type="application/rss+xml" />' . "\n";

        foreach ($items as $item) {
            $xml .= "    <item>\n";
            $xml .= '      <title>' . self::e($item->title) . '</title>' . "\n";
            $xml .= '      <link>' . self::e($item->url) . '</link>' . "\n";
            $xml .= '      <guid isPermaLink="false">' . self::e($item->guid) . '</guid>' . "\n";
            $xml .= '      <pubDate>' . $item->publishedAt->format(\DateTimeInterface::RSS) . '</pubDate>' . "\n";
            if ($item->summary !== '') {
                $xml .= '      <description>' . self::e($item->summary) . '</description>' . "\n";
            }
            if ($item->authorEmail !== '') {
                $author = $item->authorEmail . ($item->authorName !== '' ? ' (' . $item->authorName . ')' : '');
                $xml .= '      <author>' . self::e($author) . '</author>' . "\n";
            }
            $xml .= "    </item>\n";
        }

        $xml .= "  </channel>\n";
        $xml .= '</rss>' . "\n";

        return $xml;
    }

    /**
     * ساخت Atom 1.0.
     *
     * @param list<FeedItem> $items
     */
    public function atom(FeedMeta $meta, array $items): string
    {
        $updated = $items !== []
            ? $items[0]->publishedAt
            : new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="' . self::e($meta->language) . '">' . "\n";
        $xml .= '  <title>' . self::e($meta->title) . '</title>' . "\n";
        $xml .= '  <subtitle>' . self::e($meta->description) . '</subtitle>' . "\n";
        $xml .= '  <link href="' . self::e($meta->siteUrl) . '" rel="alternate" />' . "\n";
        $xml .= '  <link href="' . self::e($meta->feedUrl) . '" rel="self" />' . "\n";
        $xml .= '  <id>' . self::e($meta->feedUrl) . '</id>' . "\n";
        $xml .= '  <updated>' . $updated->format(\DateTimeInterface::ATOM) . '</updated>' . "\n";

        foreach ($items as $item) {
            $xml .= "  <entry>\n";
            $xml .= '    <title>' . self::e($item->title) . '</title>' . "\n";
            $xml .= '    <link href="' . self::e($item->url) . '" rel="alternate" />' . "\n";
            $xml .= '    <id>' . self::e($item->guid) . '</id>' . "\n";
            $xml .= '    <updated>' . $item->publishedAt->format(\DateTimeInterface::ATOM) . '</updated>' . "\n";
            if ($item->summary !== '') {
                $xml .= '    <summary type="text">' . self::e($item->summary) . '</summary>' . "\n";
            }
            if ($item->authorName !== '' || $item->authorEmail !== '') {
                $xml .= "    <author>\n";
                $xml .= '      <name>' . self::e($item->authorName !== '' ? $item->authorName : $item->authorEmail) . '</name>' . "\n";
                if ($item->authorEmail !== '') {
                    $xml .= '      <email>' . self::e($item->authorEmail) . '</email>' . "\n";
                }
                $xml .= "    </author>\n";
            }
            $xml .= "  </entry>\n";
        }

        $xml .= '</feed>' . "\n";

        return $xml;
    }

    private static function e(string $value): string
    {
        return \htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

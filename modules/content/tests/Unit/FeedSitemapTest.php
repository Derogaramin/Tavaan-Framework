<?php

declare(strict_types=1);

namespace Modules\Content\Tests\Unit;

use Modules\Content\Domain\Services\FeedBuilder;
use Modules\Content\Domain\Services\SitemapBuilder;
use Modules\Content\Domain\ValueObjects\FeedItem;
use Modules\Content\Domain\ValueObjects\FeedMeta;
use Modules\Content\Domain\ValueObjects\SitemapEntry;
use Tavaan\Testing\TestCase;

/**
 * تست واحد FeedBuilder (RSS 2.0 / Atom 1.0) و SitemapBuilder — AC-BLOG-04.
 *
 * اعتبارسنجی با simplexml (بدون I/O خارجی) + بررسی escape.
 */
final class FeedSitemapTest extends TestCase
{
    private function meta(): FeedMeta
    {
        return new FeedMeta(
            title: 'وبلاگ تَوان & دوستان',
            description: 'توضیح <فید>',
            siteUrl: 'https://example.com/blog',
            feedUrl: 'https://example.com/blog/feed',
            language: 'fa',
        );
    }

    /** @return list<FeedItem> */
    private function items(): array
    {
        return [
            new FeedItem(
                title: 'مقالهٔ اول <برچسب>',
                url: 'https://example.com/blog/first',
                guid: 'post-01HXYZ',
                summary: 'خلاصهٔ اول & دوم',
                publishedAt: new \DateTimeImmutable('2026-10-20 08:30:00', new \DateTimeZone('Asia/Tehran')),
                authorName: 'نویسنده',
                authorEmail: 'author@example.com',
            ),
            new FeedItem(
                title: 'مقالهٔ دوم',
                url: 'https://example.com/blog/second',
                guid: 'post-02HXYZ',
                summary: '',
                publishedAt: new \DateTimeImmutable('2026-10-18 10:00:00', new \DateTimeZone('Asia/Tehran')),
            ),
        ];
    }

    public function testRss_IsValidXmlWithRequiredElements(): void
    {
        $xml = (new FeedBuilder())->rss($this->meta(), $this->items());

        $prev = \libxml_use_internal_errors(true);
        $rss = \simplexml_load_string($xml);
        \libxml_use_internal_errors($prev);

        self::assertNotSame(false, $rss, 'RSS باید XML معتبر باشد');
        self::assertSame('rss', $rss?->getName());
        self::assertSame('2.0', (string) $rss['version']);

        $channel = $rss?->channel;
        self::assertSame('وبلاگ تَوان & دوستان', (string) $channel->title);
        self::assertSame('https://example.com/blog', (string) $channel->link);
        self::assertSame('توضیح <فید>', (string) $channel->description);
        self::assertSame('fa', (string) $channel->language);
        self::assertNotSame('', (string) $channel->lastBuildDate);
        self::assertCount(2, $channel->item);

        $first = $channel->item[0];
        self::assertSame('مقالهٔ اول <برچسب>', (string) $first->title);
        self::assertSame('post-01HXYZ', (string) $first->guid);
        self::assertSame('false', (string) $first->guid['isPermaLink']);
        self::assertStringContainsString('author@example.com', (string) $first->author);

        // pubDate = RFC-822 معتبر
        self::assertNotSame(false, \strtotime((string) $first->pubDate));
    }

    public function testRss_EscapesSpecialChars(): void
    {
        $xml = (new FeedBuilder())->rss($this->meta(), $this->items());
        self::assertStringContainsString('&lt;برچسب&gt;', $xml);
        self::assertStringContainsString('&amp;', $xml);
        self::assertStringNotContainsString('<برچسب>', $xml);
    }

    public function testAtom_IsValidXmlWithRequiredElements(): void
    {
        $xml = (new FeedBuilder())->atom($this->meta(), $this->items());

        $prev = \libxml_use_internal_errors(true);
        $feed = \simplexml_load_string($xml);
        \libxml_use_internal_errors($prev);

        self::assertNotSame(false, $feed, 'Atom باید XML معتبر باشد');
        self::assertSame('feed', $feed?->getName());
        self::assertSame('https://example.com/blog/feed', (string) $feed->id);
        self::assertCount(2, $feed->entry);

        $entry = $feed->entry[0];
        self::assertSame('مقالهٔ اول <برچسب>', (string) $entry->title);
        self::assertSame('post-01HXYZ', (string) $entry->id);
        self::assertNotSame(false, \strtotime((string) $entry->updated));   // RFC-3339
        self::assertSame('نویسنده', (string) $entry->author->name);
    }

    public function testAtom_EmptySummaryOmitted(): void
    {
        $xml = (new FeedBuilder())->atom($this->meta(), $this->items());
        self::assertSame(1, \substr_count($xml, '<summary'));
    }

    public function testSitemap_IsValidXml(): void
    {
        $builder = new SitemapBuilder();
        $xml = $builder->build([
            new SitemapEntry(
                loc: 'https://example.com/blog/first?a=1&b=2',
                lastmod: new \DateTimeImmutable('2026-10-20 08:30:00', new \DateTimeZone('UTC')),
                changefreq: 'daily',
                priority: 0.8,
            ),
            new SitemapEntry(loc: 'https://example.com/blog', changefreq: 'invalid', priority: 5.0),
        ]);

        $prev = \libxml_use_internal_errors(true);
        $sitemap = \simplexml_load_string($xml);
        \libxml_use_internal_errors($prev);

        self::assertNotSame(false, $sitemap, 'Sitemap باید XML معتبر باشد');
        self::assertSame('urlset', $sitemap?->getName());
        self::assertCount(2, $sitemap->url);
        self::assertStringContainsString('&amp;', $xml);                       // escape
        self::assertStringContainsString('2026-10-20T08:30:00+00:00', $xml);   // W3C lastmod
        self::assertStringContainsString('<changefreq>weekly</changefreq>', $xml);  // fallback
        self::assertStringContainsString('<priority>1.0</priority>', $xml);         // clamp
    }
}

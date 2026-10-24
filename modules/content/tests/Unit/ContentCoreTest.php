<?php

declare(strict_types=1);

namespace Modules\Content\Tests\Unit;

use Modules\Content\Domain\Enums\ContentStatus;
use Modules\Content\Domain\Services\ExcerptGenerator;
use Modules\Content\Domain\Services\JalaliMonth;
use Modules\Content\Domain\Services\Markdown;
use Modules\Content\Domain\ValueObjects\SeoMetadata;
use Modules\Content\Domain\ValueObjects\Slug;
use Tavaan\Testing\TestCase;

/**
 * تست واحد هستهٔ محتوا — Slug/SeoMetadata/ExcerptGenerator.
 *
 * پوشش: نرمال‌سازی فارسی (ی/ک/ارقام/نیم‌فاصله)، یکتاسازی، truncation،
 * fallback سئو و خطای slug خالی.
 */
final class ContentCoreTest extends TestCase
{
    // ─── Slug ─────────────────────────────────────────────────────────────

    public function testSlugify_PersianAndLatinVectors(): void
    {
        $vectors = [
            'Hello World!' => 'hello-world',
            'آموزش فریمورک تَوان' => 'آموزش-فریمورک-توان',
            'می‌شود نوشت' => 'می-شود-نوشت',                 // نیم‌فاصله → -
            'سلام دنیا ۱۲۳' => 'سلام-دنیا-123',              // ارقام فارسی → لاتین
            'كتاب و ياقوت' => 'کتاب-و-یاقوت',                // ی/ک عربی → فارسی
            '---a---b---' => 'a-b',                          // collapse + trim
            'Post #42 (Final)' => 'post-42-final',
            'Mixed متن and 2024!' => 'mixed-متن-and-2024',
        ];

        foreach ($vectors as $input => $expected) {
            self::assertSame($expected, Slug::slugify((string) $input), 'slugify: ' . $input);
        }
    }

    public function testSlug_OfAndFromRawAndToString(): void
    {
        $slug = Slug::of('سلام دنیا!');
        self::assertSame('سلام-دنیا', $slug->toString());
        self::assertSame('سلام-دنیا', (string) $slug);
        self::assertTrue($slug->equals(Slug::fromRaw('سلام-دنیا')));
    }

    public function testSlug_EmptyThrowsValidation(): void
    {
        $this->expectException(\Tavaan\Core\Exceptions\Validation\ValidationException::class);
        Slug::of('!!! ---');
    }

    public function testSlug_Unique_AppendsSuffix(): void
    {
        $existing = ['post' => true, 'post-2' => true];
        $slug = Slug::unique('post', static fn (string $c): bool => isset($existing[$c]));
        self::assertSame('post-3', $slug->toString());
    }

    public function testSlug_Unique_EmptyBaseFallsBackToItem(): void
    {
        $slug = Slug::unique('!!!', static fn (string $c): bool => false);
        self::assertSame('item', $slug->toString());
    }

    // ─── SeoMetadata ──────────────────────────────────────────────────────

    public function testSeo_TruncatesTitleAndDescription(): void
    {
        $longTitle = \str_repeat('طولانی ', 30);
        $seo = SeoMetadata::make(title: $longTitle, plainBody: \str_repeat('متن ', 100));

        self::assertLessThanOrEqual(62, \mb_strlen($seo->title));   // 60 + «…»
        self::assertLessThanOrEqual(162, \mb_strlen($seo->description));
        self::assertStringContainsString('…', $seo->title);
    }

    public function testSeo_ExplicitDescriptionWins(): void
    {
        $seo = SeoMetadata::make(title: 'عنوان', plainBody: 'بدنه', description: 'توضیح صریح');
        self::assertSame('توضیح صریح', $seo->description);
    }

    public function testSeo_MetaAndOpenGraphTags(): void
    {
        $seo = SeoMetadata::make(
            title: 'عنوان',
            description: 'توضیح',
            keywords: 'کلید,واژه',
            canonical: 'https://example.com/blog/post',
            ogImage: 'https://example.com/img.png',
        );

        $meta = $seo->metaTags();
        self::assertSame('عنوان', $meta['title']);
        self::assertSame('توضیح', $meta['description']);
        self::assertSame('کلید,واژه', $meta['keywords']);

        $og = $seo->openGraph();
        self::assertSame('article', $og['og:type']);
        self::assertSame('https://example.com/blog/post', $og['og:url']);
        self::assertSame('https://example.com/img.png', $og['og:image']);
        self::assertFalse($seo->isEmpty());
    }

    // ─── ExcerptGenerator ─────────────────────────────────────────────────

    public function testExcerpt_StripsHtmlAndDecodesEntities(): void
    {
        $gen = new ExcerptGenerator(defaultChars: 40);
        $excerpt = $gen->fromHtml('<p>سلام &lt;دنیا&gt; &amp; دوستان</p>');
        self::assertSame('سلام <دنیا> & دوستان', $excerpt);
    }

    public function testExcerpt_TruncatesAtWordBoundary(): void
    {
        $gen = new ExcerptGenerator(defaultChars: 20);
        $excerpt = $gen->fromHtml('کلمهٔ اول کلمهٔ دوم کلمهٔ سوم کلمهٔ چهارم');
        self::assertStringContainsString('…', $excerpt);
        self::assertLessThanOrEqual(22, \mb_strlen($excerpt));
        self::assertFalse(\str_ends_with($excerpt, ' …'));
    }

    // ─── JalaliMonth ──────────────────────────────────────────────────────

    public function testJalaliMonth_ArchiveRangeVectors(): void
    {
        // بردارهای تأییدشده با موتور مرجع Jalali (jalaali-js breaks)
        $meh = JalaliMonth::fromRoute('1403', '7');
        self::assertSame('2024-09-22 00:00:00', $meh->start()->format('Y-m-d H:i:s'));
        self::assertSame('2024-10-22 00:00:00', $meh->endExclusive()->format('Y-m-d H:i:s'));
        self::assertSame(30, $meh->length());

        $esfand1402 = new JalaliMonth(1402, 12);
        self::assertSame('2024-02-20 00:00:00', $esfand1402->start()->format('Y-m-d H:i:s'));
        self::assertSame(29, $esfand1402->length());   // ۱۴۰۲ غیرکبیسه

        $esfand1403 = new JalaliMonth(1403, 12);
        self::assertSame('2025-02-19 00:00:00', $esfand1403->start()->format('Y-m-d H:i:s'));
        self::assertSame(30, $esfand1403->length());   // ۱۴۰۳ کبیسه
        self::assertTrue(JalaliMonth::isLeap(1403));
        self::assertFalse(JalaliMonth::isLeap(1402));

        $farvardin = new JalaliMonth(1404, 1);
        self::assertSame('2025-03-21 00:00:00', $farvardin->start()->format('Y-m-d H:i:s'));
    }

    public function testJalaliMonth_RoundTripConversion(): void
    {
        foreach ([[2024, 9, 22, 1403, 7, 1], [2025, 3, 20, 1403, 12, 30], [2024, 3, 20, 1403, 1, 1]] as [$gy, $gm, $gd, $jy, $jm, $jd]) {
            self::assertSame([$jy, $jm, $jd], JalaliMonth::gregorianToJalali($gy, $gm, $gd));
            self::assertSame([$gy, $gm, $gd], JalaliMonth::jalaliToGregorian($jy, $jm, $jd));
        }
    }

    public function testJalaliMonth_Navigation(): void
    {
        $m = new JalaliMonth(1403, 1);
        self::assertSame([1403, 2], [$m->next()->year, $m->next()->month]);
        self::assertSame([1402, 12], [$m->previous()->year, $m->previous()->month]);

        $esfand = new JalaliMonth(1403, 12);
        self::assertSame([1404, 1], [$esfand->next()->year, $esfand->next()->month]);
    }

    public function testJalaliMonth_LabelFa(): void
    {
        self::assertSame('مهر ۱۴۰۳', (new JalaliMonth(1403, 7))->labelFa());
    }

    public function testJalaliMonth_InvalidInputThrows(): void
    {
        $this->expectException(\OutOfRangeException::class);
        JalaliMonth::fromRoute('1403', '13');
    }

    // ─── ContentStatus ────────────────────────────────────────────────────

    public function testContentStatus_TransitionMatrix(): void
    {
        $allowed = [
            [ContentStatus::Draft, ContentStatus::Published, true],
            [ContentStatus::Draft, ContentStatus::Scheduled, true],
            [ContentStatus::Draft, ContentStatus::Archived, true],
            [ContentStatus::Scheduled, ContentStatus::Published, true],
            [ContentStatus::Scheduled, ContentStatus::Draft, true],
            [ContentStatus::Published, ContentStatus::Archived, true],
            [ContentStatus::Published, ContentStatus::Draft, true],
            [ContentStatus::Published, ContentStatus::Scheduled, false],
            [ContentStatus::Archived, ContentStatus::Draft, true],
            [ContentStatus::Archived, ContentStatus::Published, false],
            [ContentStatus::Draft, ContentStatus::Draft, false],
        ];

        foreach ($allowed as [$from, $to, $expected]) {
            self::assertSame($expected, $from->canTransitionTo($to), $from->value . '→' . $to->value);
        }
    }

    public function testContentStatus_PublicVisibility(): void
    {
        self::assertTrue(ContentStatus::Published->isPublic());
        self::assertFalse(ContentStatus::Draft->isPublic());
        self::assertFalse(ContentStatus::Scheduled->isPublic());
        self::assertFalse(ContentStatus::Archived->isPublic());
        self::assertSame('draft', ContentStatus::Draft->value);
        self::assertSame('منتشرشده', ContentStatus::Published->labelFa());
    }

    // ─── Markdown (XSS و تبدیل) ──────────────────────────────────────────

    public function testMarkdown_BasicConversions(): void
    {
        $md = new Markdown();

        self::assertStringContainsString('<h2>عنوان</h2>', $md->toHtml('## عنوان'));
        self::assertStringContainsString('<strong>bold</strong>', $md->toHtml('متن **bold** ادامه'));
        self::assertStringContainsString('<em>italic</em>', $md->toHtml('متن *italic* ادامه'));
        self::assertStringContainsString('<del>خط</del>', $md->toHtml('~~خط~~'));
        self::assertStringContainsString('<code>inline</code>', $md->toHtml('کد `inline` اینجا'));
        self::assertStringContainsString('<hr />', $md->toHtml("---\n"));
        self::assertStringContainsString('<ul>', $md->toHtml("- آیتم ۱\n- آیتم ۲"));
        self::assertStringContainsString('<ol>', $md->toHtml("1. اول\n2. دوم"));
        self::assertStringContainsString('<blockquote>', $md->toHtml('> نقل قول'));
        self::assertStringContainsString('<p>پاراگراف</p>', $md->toHtml('پاراگراف'));
    }

    public function testMarkdown_FencedCodeKeepsContentRaw(): void
    {
        $md = new Markdown();
        $html = $md->toHtml("```php\necho '<b>hi</b>';\n```");

        self::assertStringContainsString('<pre><code class="language-php">', $html);
        self::assertStringContainsString('&lt;b&gt;', $html);      // escaped داخل کد
        self::assertStringNotContainsString('<b>hi</b>', $html);
    }

    public function testMarkdown_LinksAllowedSchemesOnly(): void
    {
        $md = new Markdown();

        $ok = $md->toHtml('[سایت](https://example.com/x?a=1&b=2)');
        self::assertStringContainsString('<a href="https://example.com/x?a=1&amp;b=2"', $ok);
        self::assertStringContainsString('rel="nofollow noopener"', $ok);

        $relative = $md->toHtml('[داخلی](/blog/post)');
        self::assertStringContainsString('href="/blog/post"', $relative);

        $js = $md->toHtml('[بد](javascript:alert(1))');
        self::assertStringNotContainsString('<a ', $js);            // لینک حذف شد
        self::assertStringContainsString('بد', $js);                 // متن باقی است
        self::assertStringNotContainsString('javascript:', $js);
    }

    public function testMarkdown_ImagesRenderedOrStripped(): void
    {
        $withImages = new Markdown(allowImages: true);
        self::assertStringContainsString('<img src="https://example.com/a.png" alt="alt"', $withImages->toHtml('![alt](https://example.com/a.png)'));

        $noImages = new Markdown(allowImages: false);
        self::assertStringNotContainsString('<img', $noImages->toHtml('![alt](https://example.com/a.png)'));
    }

    public function testMarkdown_XssVectorsNeutralized(): void
    {
        $md = new Markdown();

        $vectors = [
            '<script>alert(1)</script>',
            '<img src=x onerror=alert(1)>',
            '<a href="x" onclick="alert(1)">کلیک</a>',
            '"><svg onload=alert(1)>',
            '[کلیک](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)',
            '[بد](JaVaScRiPt:alert(1))',
            '<iframe src="https://evil.com"></iframe>',
            "متن عادی\n\n<script>evil()</script>",
        ];

        foreach ($vectors as $vector) {
            $html = $md->toHtml($vector);

            // هیچ تگ خامی جز تگ‌های تولیدشدهٔ مجاز نباید در خروجی بماند
            $stripped = (string) \preg_replace(
                '#</?(?:p|h[1-6]|ul|ol|li|blockquote|hr|pre|code|strong|em|del|a|img)(?:\s[^<>]*)?\s*/?>#',
                '',
                $html,
            );
            self::assertStringNotContainsString('<', $stripped, 'تگ خام نشت کرد برای: ' . $vector);
            self::assertStringNotContainsString('javascript:', \mb_strtolower($html));
        }
    }

    public function testMarkdown_PersianContentIntact(): void
    {
        $md = new Markdown();
        $html = $md->toHtml("# عنوان فارسی\n\nاین یک **پاراگراف** فارسی با نیم‌فاصله است.");
        self::assertStringContainsString('عنوان فارسی', $html);
        self::assertStringContainsString('نیم‌فاصله', $html);
        self::assertStringContainsString('<strong>پاراگراف</strong>', $html);
    }
}

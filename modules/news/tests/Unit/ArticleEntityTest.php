<?php

declare(strict_types=1);

namespace Modules\News\Tests\Unit;

use Modules\Content\Domain\ValueObjects\SeoMetadata;
use Modules\News\Domain\Entities\Article;
use Modules\News\Domain\Entities\NewsCategory;
use Modules\News\Domain\Enums\ArticleStatus;
use Modules\News\Domain\Events\ArticleWasApproved;
use Modules\News\Domain\Events\ArticleWasPublished;
use Modules\News\Domain\Events\ArticleWasRejected;
use Modules\News\Domain\Events\ArticleWasSubmitted;
use Modules\News\Domain\Events\BreakingNewsWasPublished;
use Modules\News\Domain\Services\JsonLdBuilder;
use Tavaan\Testing\TestCase;

/**
 * تست واحد دامنهٔ خبرگزاری — Article aggregate + workflow (DOC-092 §3) + JSON-LD.
 */
final class ArticleEntityTest extends TestCase
{
    private function article(): Article
    {
        return Article::draft(
            slug: 'test-news',
            title: 'خبر آزمایشی',
            authorId: 3,
            lead: 'لید خبر',
            body: '<p>بدنه</p>',
            categoryId: 2,
            seo: SeoMetadata::make(title: 'سئو', plainBody: 'لید خبر'),
        );
    }

    // ─── ArticleStatus ────────────────────────────────────────────────────

    public function testStatus_WorkflowMatrix(): void
    {
        $allowed = [
            [ArticleStatus::Draft, ArticleStatus::Review, true],
            [ArticleStatus::Draft, ArticleStatus::Published, false],   // باید بازبینی شود
            [ArticleStatus::Draft, ArticleStatus::Archived, true],
            [ArticleStatus::Review, ArticleStatus::Approved, true],
            [ArticleStatus::Review, ArticleStatus::Draft, true],       // reject
            [ArticleStatus::Review, ArticleStatus::Published, false],  // بدون تأیید منتشر نمی‌شود
            [ArticleStatus::Approved, ArticleStatus::Published, true],
            [ArticleStatus::Approved, ArticleStatus::Draft, true],
            [ArticleStatus::Approved, ArticleStatus::Archived, true],
            [ArticleStatus::Published, ArticleStatus::Archived, true],
            [ArticleStatus::Published, ArticleStatus::Draft, true],    // unpublish
            [ArticleStatus::Published, ArticleStatus::Approved, false],
            [ArticleStatus::Archived, ArticleStatus::Draft, true],
            [ArticleStatus::Archived, ArticleStatus::Published, false],
            [ArticleStatus::Draft, ArticleStatus::Draft, false],
        ];

        foreach ($allowed as [$from, $to, $expected]) {
            self::assertSame($expected, $from->canTransitionTo($to), $from->value . '→' . $to->value);
        }
    }

    public function testStatus_VisibilityAndPipeline(): void
    {
        self::assertTrue(ArticleStatus::Published->isPublic());
        self::assertFalse(ArticleStatus::Approved->isPublic());
        self::assertTrue(ArticleStatus::Review->isInPipeline());
        self::assertTrue(ArticleStatus::Draft->isInPipeline());
        self::assertTrue(ArticleStatus::Approved->isInPipeline());
        self::assertFalse(ArticleStatus::Published->isInPipeline());
        self::assertSame('در بازبینی', ArticleStatus::Review->labelFa());
    }

    // ─── Article workflow ─────────────────────────────────────────────────

    public function testDraft_Defaults(): void
    {
        $a = $this->article();
        self::assertSame(26, \strlen($a->id()));
        self::assertSame(ArticleStatus::Draft, $a->status());
        self::assertNull($a->editorId());
        self::assertFalse($a->isBreaking());
        self::assertFalse($a->commentsOpen());
        self::assertSame([], $a->gallery());
    }

    public function testWorkflow_SubmitApprovePublish_FullChain(): void
    {
        $a = $this->article();

        $a->submitForReview();
        self::assertSame(ArticleStatus::Review, $a->status());
        $events = $a->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ArticleWasSubmitted::class, $events[0]);
        self::assertSame('news.article.submitted', $events[0]->eventName());

        $a->approve(editorId: 9, scheduledAt: new \DateTimeImmutable('2026-12-01 08:00:00', new \DateTimeZone('UTC')));
        self::assertSame(ArticleStatus::Approved, $a->status());
        self::assertSame(9, $a->editorId());
        self::assertNotNull($a->scheduledAt());
        $events = $a->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ArticleWasApproved::class, $events[0]);

        // هنوز عمومی نیست (زمان‌بندی‌شده)
        self::assertFalse($a->isPublished());
        self::assertTrue($a->isDueForPublishing(new \DateTimeImmutable('2026-12-02 00:00:00', new \DateTimeZone('UTC'))));
        self::assertFalse($a->isDueForPublishing(new \DateTimeImmutable('2026-11-01 00:00:00', new \DateTimeZone('UTC'))));

        $a->publish(editorId: 9);
        self::assertSame(ArticleStatus::Published, $a->status());
        self::assertNull($a->scheduledAt());                 // پس از انتشار پاک می‌شود
        self::assertNotNull($a->publishedAt());
        self::assertTrue($a->isPublished());
        self::assertTrue($a->commentsOpen());
    }

    public function testWorkflow_RejectBackToDraftWithReason(): void
    {
        $a = $this->article();
        $a->submitForReview();
        $a->pullEvents();

        $a->reject(editorId: 5, reason: 'تیتر گنگ است');
        self::assertSame(ArticleStatus::Draft, $a->status());
        self::assertSame(5, $a->editorId());
        self::assertSame('تیتر گنگ است', $a->rejectReason());

        $events = $a->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ArticleWasRejected::class, $events[0]);
        self::assertSame('تیتر گنگ است', $events[0]->toPayload()['reason']);
    }

    public function testWorkflow_InvalidTransitionThrows(): void
    {
        $a = $this->article();

        $this->expectException(\DomainException::class);
        $a->publish(editorId: 1);   // draft → published مجاز نیست
    }

    public function testPublish_BackdatesPublishedAt(): void
    {
        $a = $this->article();
        $a->submitForReview();
        $a->approve(editorId: 2);
        $a->publish(editorId: 2, at: new \DateTimeImmutable('2025-05-05 12:00:00', new \DateTimeZone('UTC')));

        self::assertSame('2025-05-05 12:00:00', $a->publishedAt()?->format('Y-m-d H:i:s'));
    }

    // ─── Breaking ─────────────────────────────────────────────────────────

    public function testBreaking_OnPublishEmitsEvent(): void
    {
        $a = $this->article();
        $a->submitForReview();
        $a->approve(editorId: 2);
        $a->setBreaking(true);          // approved → مجاز
        $a->pullEvents();

        $a->publish(editorId: 2);
        $events = $a->pullEvents();

        self::assertCount(2, $events);   // published + breaking
        self::assertInstanceOf(ArticleWasPublished::class, $events[0]);
        self::assertInstanceOf(BreakingNewsWasPublished::class, $events[1]);
        self::assertTrue($events[0]->toPayload()['is_breaking']);
    }

    public function testBreaking_ToggleOnPublishedEmitsOnce(): void
    {
        $a = $this->article();
        $a->submitForReview();
        $a->approve(editorId: 2);
        $a->publish(editorId: 2);
        $a->pullEvents();

        self::assertTrue($a->setBreaking(true));
        $events = $a->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BreakingNewsWasPublished::class, $events[0]);

        self::assertFalse($a->setBreaking(true));   // بدون تغییر → رویداد ندارد
        self::assertSame([], $a->pullEvents());

        self::assertTrue($a->setBreaking(false));
        self::assertSame([], $a->pullEvents());     // برداشتن علامت → رویداد ندارد
    }

    public function testBreaking_OnDraftThrows(): void
    {
        $a = $this->article();

        $this->expectException(\DomainException::class);
        $a->setBreaking(true);
    }

    // ─── گالری/ویرایش/ردیف ────────────────────────────────────────────────

    public function testRevise_GalleryAndRowRoundTrip(): void
    {
        $a = $this->article();
        $a->revise(
            title: 'عنوان تازه',
            lead: 'لید تازه',
            body: '<p>بدنهٔ تازه</p>',
            categoryId: null,
            gallery: ['01HZX0000000000000000000AA', '01HZX0000000000000000000BB'],
        );

        self::assertSame('عنوان تازه', $a->title());
        self::assertNull($a->categoryId());
        self::assertCount(2, $a->gallery());

        $row = $a->toRow();
        self::assertIsString($row['gallery']);      // JSON در DB
        $rebuilt = Article::fromRow($row);
        self::assertSame($a->gallery(), $rebuilt->gallery());
        self::assertSame($a->id(), $rebuilt->id());
        self::assertSame(ArticleStatus::Draft, $rebuilt->status());
        self::assertSame('سئو', $rebuilt->seo()->title);
    }

    public function testSoftDelete_AndContracts(): void
    {
        $a = $this->article();
        $a->submitForReview();
        $a->approve(editorId: 1);
        $a->publish(editorId: 1);

        self::assertSame('news_article', $a->commentableType());
        self::assertSame('news_article', $a->mediaSubjectType());
        self::assertSame($a->id(), $a->commentableId());

        $a->softDelete();
        self::assertTrue($a->isTrashed());
        self::assertFalse($a->isPublished());
        self::assertFalse($a->commentsOpen());
    }

    // ─── NewsCategory ─────────────────────────────────────────────────────

    public function testCategory_Nested(): void
    {
        $parent = NewsCategory::fromRow(['id' => 1, 'title' => 'فناوری', 'slug' => 'tech', 'parent_id' => null]);
        $child = NewsCategory::create('هوش مصنوعی', 'ai', parentId: 1);

        self::assertNull($child->id());
        self::assertSame(1, $child->toRow()['parent_id']);
        self::assertNull($parent->parentId());

        $child->edit('AI');
        self::assertSame('AI', $child->title());
    }

    // ─── JsonLdBuilder (DOC-092 §7) ───────────────────────────────────────

    public function testJsonLd_NewsArticleSchema(): void
    {
        $a = $this->article();
        $a->submitForReview();
        $a->approve(editorId: 1);
        $a->publish(editorId: 1, at: new \DateTimeImmutable('2026-10-20 08:00:00', new \DateTimeZone('UTC')));

        $author = new \Modules\CoreUser\Contracts\UserSnapshot(id: 3, name: 'نگار', email: 'n@x.dev');
        $jsonLd = (new JsonLdBuilder())->forArticle($a, $author, '/news/test-news', '/img/cover.jpg');

        self::assertSame('https://schema.org', $jsonLd['@context']);
        self::assertSame('NewsArticle', $jsonLd['@type']);
        self::assertSame('خبر آزمایشی', $jsonLd['headline']);
        self::assertSame('fa', $jsonLd['inLanguage']);
        self::assertSame('نگار', $jsonLd['author']['name']);
        self::assertSame('2026-10-20T08:00:00+00:00', $jsonLd['datePublished']);
        self::assertSame('/news/test-news', $jsonLd['mainEntityOfPage']['@id']);
        self::assertSame('/img/cover.jpg', $jsonLd['image']);
        self::assertSame('لید خبر', $jsonLd['description']);
    }
}

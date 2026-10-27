<?php

declare(strict_types=1);

namespace Modules\Content\Tests\Unit;

use Modules\Content\Domain\Entities\Page;
use Modules\Content\Domain\Enums\ContentStatus;
use Modules\Content\Domain\Events\PageWasPublished;
use Modules\Content\Domain\Events\PageWasUpdated;
use Modules\Content\Domain\ValueObjects\SeoMetadata;
use Tavaan\Testing\TestCase;

/**
 * تست دامنهٔ Page — کارخانه، ماتریس انتقال وضعیت، رویدادها، Commentable،
 * soft-delete و roundtrip نگاشت ردیف.
 */
final class PageDomainTest extends TestCase
{
    private function makePage(
        string $slug = 'درباره-ما',
        string $title = 'دربارهٔ ما',
        bool $commentsOpen = true,
    ): Page {
        return Page::draft(
            slug: $slug,
            title: $title,
            authorId: 7,
            excerpt: 'خلاصهٔ آزمایشی',
            body: '<p>بدنهٔ آزمایشی</p>',
            seo: SeoMetadata::make(
                title: 'عنوان سئو',
                plainBody: 'خلاصهٔ آزمایشی',
                description: 'توضیح سئو',
                keywords: 'کلید، واژه',
            ),
            commentsOpen: $commentsOpen,
        );
    }

    // ─── کارخانه و قراردادها ───────────────────────────────────────────────

    public function testDraftFactoryDefaults(): void
    {
        $page = $this->makePage();

        self::assertSame(26, \strlen($page->id()));
        self::assertSame(ContentStatus::Draft, $page->status());
        self::assertSame(0, $page->viewCount());
        self::assertNull($page->publishedAt());
        self::assertNotNull($page->createdAt());
        self::assertFalse($page->isPublished());
        self::assertFalse($page->isTrashed());
        self::assertTrue($page->commentsOpenFlag());
        // draft هنوز عمومی نیست → نظرات باز نیست
        self::assertFalse($page->commentsOpen());
    }

    public function testCommentableContract(): void
    {
        $page = $this->makePage(slug: 'about-us');

        self::assertSame('page', $page->commentableType());
        self::assertSame('about-us', $page->commentableId());
    }

    // ─── ماتریس وضعیت ─────────────────────────────────────────────────────

    public function testPublishSetsStatusDateAndEvent(): void
    {
        $page = $this->makePage();
        $page->publish();

        self::assertSame(ContentStatus::Published, $page->status());
        self::assertNotNull($page->publishedAt());
        self::assertTrue($page->isPublished());
        self::assertTrue($page->commentsOpen());

        $events = $page->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PageWasPublished::class, $events[0]);
        self::assertSame('content.page.published', $events[0]->eventName());
        self::assertSame($page->id(), $events[0]->pageId);
        // pull دوباره → خالی
        self::assertCount(0, $page->pullEvents());
    }

    public function testPublishAcceptsExplicitDate(): void
    {
        $at = new \DateTimeImmutable('2026-01-02 03:04:05', new \DateTimeZone('UTC'));
        $page = $this->makePage();
        $page->publish($at);

        self::assertSame('2026-01-02 03:04:05', $page->publishedAt()?->format('Y-m-d H:i:s'));
    }

    public function testArchivedCannotBePublishedAgain(): void
    {
        $page = $this->makePage();
        $page->publish();
        $page->archive();
        self::assertSame(ContentStatus::Archived, $page->status());

        $this->expectException(\DomainException::class);
        $page->publish();
    }

    public function testArchiveKeepsPublishedAt(): void
    {
        $at = new \DateTimeImmutable('2026-02-03 10:00:00', new \DateTimeZone('UTC'));
        $page = $this->makePage();
        $page->publish($at);
        $page->archive();

        self::assertSame('2026-02-03 10:00:00', $page->publishedAt()?->format('Y-m-d H:i:s'));
    }

    public function testBackToDraftFromPublished(): void
    {
        $page = $this->makePage();
        $page->publish();
        $page->backToDraft();

        self::assertSame(ContentStatus::Draft, $page->status());
        self::assertFalse($page->isPublished());
    }

    // ─── ویرایش و رویدادها ────────────────────────────────────────────────

    public function testReviseUpdatesFieldsAndRecordsEvent(): void
    {
        $page = $this->makePage();
        $page->revise(
            title: 'عنوان تازه',
            slug: null,
            excerpt: 'خلاصهٔ تازه',
            body: '<p>بدنهٔ تازه</p>',
            seo: SeoMetadata::make(title: 'سئوی تازه', plainBody: 'خلاصهٔ تازه'),
            commentsOpen: false,
        );

        self::assertSame('عنوان تازه', $page->title());
        self::assertSame('خلاصهٔ تازه', $page->excerpt());
        self::assertSame('سئوی تازه', $page->seo()->title);
        self::assertFalse($page->commentsOpenFlag());
        self::assertNotNull($page->updatedAt());

        $events = $page->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PageWasUpdated::class, $events[0]);
        self::assertSame('content.page.updated', $events[0]->eventName());
    }

    public function testChangeSlug(): void
    {
        $page = $this->makePage(slug: 'old-slug');
        $page->changeSlug('new-slug');

        self::assertSame('new-slug', $page->slug());
        self::assertSame('new-slug', $page->commentableId());
    }

    public function testCommentsClosedFlagBlocksEvenWhenPublished(): void
    {
        $page = $this->makePage(commentsOpen: false);
        $page->publish();

        self::assertTrue($page->isPublished());
        self::assertFalse($page->commentsOpenFlag());
        self::assertFalse($page->commentsOpen());
    }

    // ─── حذف نرم و بازدید ─────────────────────────────────────────────────

    public function testSoftDeleteAndRestore(): void
    {
        $page = $this->makePage();
        $page->publish();

        $page->softDelete();
        self::assertTrue($page->isTrashed());
        self::assertFalse($page->isPublished());
        self::assertFalse($page->commentsOpen());

        $page->restore();
        self::assertFalse($page->isTrashed());
        self::assertTrue($page->isPublished());
    }

    public function testIncrementViewsNeverNegative(): void
    {
        $page = $this->makePage();
        $page->incrementViews(5);
        self::assertSame(5, $page->viewCount());

        $page->incrementViews(-10);
        self::assertSame(0, $page->viewCount());
    }

    // ─── نگاشت ردیف ───────────────────────────────────────────────────────

    public function testToRowFromRowRoundtrip(): void
    {
        $at = new \DateTimeImmutable('2026-03-04 05:06:07.123', new \DateTimeZone('UTC'));
        $page = $this->makePage(slug: 'roundtrip');
        $page->publish($at);
        $page->incrementViews(3);

        $copy = Page::fromRow($page->toRow());

        self::assertSame($page->id(), $copy->id());
        self::assertSame('roundtrip', $copy->slug());
        self::assertSame($page->title(), $copy->title());
        self::assertSame($page->excerpt(), $copy->excerpt());
        self::assertSame($page->body(), $copy->body());
        self::assertSame(7, $copy->authorId());
        self::assertSame(ContentStatus::Published, $copy->status());
        self::assertSame(3, $copy->viewCount());
        self::assertSame('2026-03-04 05:06:07.123', $copy->publishedAt()?->format('Y-m-d H:i:s.v'));
        self::assertSame('عنوان سئو', $copy->seo()->title);
        self::assertSame('توضیح سئو', $copy->seo()->description);
        self::assertSame('کلید، واژه', $copy->seo()->keywords);
        self::assertTrue($copy->commentsOpenFlag());
        self::assertFalse($copy->isTrashed());
    }

    public function testFromRowToleratesMissingColumns(): void
    {
        $page = Page::fromRow(['id' => 'X1', 'slug' => 'minimal', 'title' => 'مینیمال']);

        self::assertSame(ContentStatus::Draft, $page->status());
        self::assertNull($page->publishedAt());
        self::assertSame('', $page->seo()->title);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Faq\Tests\Unit;

use Modules\Content\Domain\Enums\ContentStatus;
use Modules\Faq\Domain\Entities\FaqCategory;
use Modules\Faq\Domain\Entities\FaqItem;
use Modules\Faq\Domain\Events\FaqItemWasPublished;
use Modules\Faq\Domain\Events\FaqItemWasUpdated;
use PHPUnit\Framework\TestCase;

/**
 * تست دامنهٔ پرسش‌های متداول — موجودیت‌های FaqCategory/FaqItem،
 * ماتریس انتقال وضعیت (ContentStatus)، رویدادها و نگاشت سطر.
 */
final class FaqDomainTest extends TestCase
{
    // ─── FaqCategory ──────────────────────────────────────────────────────

    public function testCategoryMakeGeneratesUlidAndTrimsTitle(): void
    {
        $category = FaqCategory::make('  عمومی  ', 'omumi', 3);

        self::assertSame(26, \strlen($category->id()));
        self::assertSame('عمومی', $category->title());
        self::assertSame('omumi', $category->slug());
        self::assertSame(3, $category->sortOrder());
        self::assertFalse($category->isTrashed());
        self::assertNotNull($category->createdAt());
    }

    public function testCategoryMakeRejectsEmptyTitle(): void
    {
        $this->expectException(\DomainException::class);
        FaqCategory::make('   ', 'slug');
    }

    public function testCategoryReviseAndSoftDelete(): void
    {
        $category = FaqCategory::make('دستهٔ آزمون', 'daste-azmoon');

        $category->revise(title: 'عنوان تازه', sortOrder: 7);
        self::assertSame('عنوان تازه', $category->title());
        self::assertSame(7, $category->sortOrder());
        self::assertNotNull($category->updatedAt());

        $category->changeSlug('slug-taze');
        self::assertSame('slug-taze', $category->slug());

        $category->softDelete();
        self::assertTrue($category->isTrashed());

        $category->restore();
        self::assertFalse($category->isTrashed());
    }

    public function testCategoryRowRoundtrip(): void
    {
        $category = FaqCategory::make('گردشگری', 'gardeshgari', 2);
        $row = $category->toRow();
        $restored = FaqCategory::fromRow($row);

        self::assertSame($category->id(), $restored->id());
        self::assertSame('گردشگری', $restored->title());
        self::assertSame('gardeshgari', $restored->slug());
        self::assertSame(2, $restored->sortOrder());
        self::assertFalse($restored->isTrashed());
    }

    // ─── FaqItem — ساخت و نگاشت ───────────────────────────────────────────

    public function testDraftDefaults(): void
    {
        $item = FaqItem::draft('چگونه ثبت‌نام کنم؟', '<p>از دکمهٔ ثبت‌نام استفاده کنید.</p>', null, 42, 5);

        self::assertSame(26, \strlen($item->id()));
        self::assertSame(ContentStatus::Draft, $item->status());
        self::assertNull($item->categoryId());
        self::assertSame(42, $item->authorId());
        self::assertSame(5, $item->sortOrder());
        self::assertSame(0, $item->helpfulYes());
        self::assertSame(0, $item->helpfulNo());
        self::assertFalse($item->isVisible());
    }

    public function testDraftRejectsEmptyQuestion(): void
    {
        $this->expectException(\DomainException::class);
        FaqItem::draft('  ', 'پاسخ');
    }

    public function testItemRowRoundtripWithCategory(): void
    {
        $item = FaqItem::draft('پرسش گرد؟', '<p>پاسخ</p>', str_repeat('0', 26), 7, 1);
        $item->publish();
        $row = $item->toRow();

        $restored = FaqItem::fromRow($row);
        self::assertSame($item->id(), $restored->id());
        self::assertSame(str_repeat('0', 26), $restored->categoryId());
        self::assertSame(ContentStatus::Published, $restored->status());
        self::assertSame('<p>پاسخ</p>', $restored->answer());
        self::assertTrue($restored->isVisible());
    }

    public function testFromRowToleratesMissingColumns(): void
    {
        $item = FaqItem::fromRow(['id' => str_repeat('a', 26), 'question' => 'پرسش؟']);

        self::assertSame(ContentStatus::Draft, $item->status());
        self::assertNull($item->categoryId());
        self::assertSame('', $item->answer());
        self::assertSame(0, $item->helpfulYes());
    }

    // ─── FaqItem — چرخهٔ وضعیت ────────────────────────────────────────────

    public function testPublishArchiveBackToDraftLifecycle(): void
    {
        $item = FaqItem::draft('پرسش چرخه؟', 'پاسخ');

        $item->publish();
        self::assertSame(ContentStatus::Published, $item->status());
        self::assertTrue($item->isPublished());

        $item->archive();
        self::assertSame(ContentStatus::Archived, $item->status());

        $item->backToDraft();
        self::assertSame(ContentStatus::Draft, $item->status());
    }

    public function testArchivedCannotPublishDirectly(): void
    {
        $item = FaqItem::draft('پرسش بایگانی؟', 'پاسخ');
        $item->publish();
        $item->archive();

        $this->expectException(\DomainException::class);
        $item->publish();
    }

    public function testDoublePublishThrows(): void
    {
        $item = FaqItem::draft('پرسش دوبار؟', 'پاسخ');
        $item->publish();

        $this->expectException(\DomainException::class);
        $item->publish();
    }

    public function testVisibilityRequiresPublishedAndNotTrashed(): void
    {
        $item = FaqItem::draft('پرسش visibility؟', 'پاسخ');
        $item->publish();
        self::assertTrue($item->isVisible());

        $item->softDelete();
        self::assertTrue($item->isTrashed());
        self::assertFalse($item->isVisible());

        $item->restore();
        self::assertTrue($item->isVisible());
    }

    // ─── FaqItem — ویرایش و رأی ───────────────────────────────────────────

    public function testReviseKeepsStatusAndCounters(): void
    {
        $item = FaqItem::draft('پرسش کهنه؟', '<p>پاسخ کهنه</p>');
        $item->publish();
        $item->recordHelpful(true);

        $item->revise('پرسش تازه؟', '<p>پاسخ تازه</p>', sortOrder: 9);

        self::assertSame('پرسش تازه؟', $item->question());
        self::assertSame('<p>پاسخ تازه</p>', $item->answer());
        self::assertSame(ContentStatus::Published, $item->status());
        self::assertSame(9, $item->sortOrder());
        self::assertSame(1, $item->helpfulYes());
    }

    public function testReviseClearCategory(): void
    {
        $item = FaqItem::draft('پرسش با دسته؟', 'پاسخ', str_repeat('b', 26));
        self::assertSame(str_repeat('b', 26), $item->categoryId());

        $item->revise('پرسش با دسته؟', null, null, null, clearCategory: true);
        self::assertNull($item->categoryId());
    }

    public function testRecordHelpfulAccumulates(): void
    {
        $item = FaqItem::draft('پرسش رأی؟', 'پاسخ');

        $item->recordHelpful(true);
        $item->recordHelpful(true);
        $item->recordHelpful(false);

        self::assertSame(2, $item->helpfulYes());
        self::assertSame(1, $item->helpfulNo());
    }

    // ─── رویدادها ─────────────────────────────────────────────────────────

    public function testPublishRecordsPublishedEvent(): void
    {
        $item = FaqItem::draft('پرسش رویداد؟', 'پاسخ');
        $item->publish();

        $events = $item->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(FaqItemWasPublished::class, $events[0]);
        self::assertSame('faq.item.published', $events[0]->eventName());
        self::assertSame($item->id(), $events[0]->itemId);

        // pull باید صف را خالی کند
        self::assertSame([], $item->pullEvents());
    }

    public function testReviseRecordsUpdatedEvent(): void
    {
        $item = FaqItem::draft('پرسش ویرایش؟', 'پاسخ');
        $item->revise('پرسش ویرایش‌شده؟');

        $events = $item->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(FaqItemWasUpdated::class, $events[0]);
        self::assertSame('faq.item.updated', $events[0]->eventName());
    }
}

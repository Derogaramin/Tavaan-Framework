<?php

declare(strict_types=1);

namespace Modules\Blog\Tests\Unit;

use Modules\Blog\Domain\Entities\Category;
use Modules\Blog\Domain\Entities\Post;
use Modules\Blog\Domain\Events\PostWasPublished;
use Modules\Blog\Domain\Events\PostWasUpdated;
use Modules\Content\Domain\Enums\ContentStatus;
use Modules\Content\Domain\ValueObjects\SeoMetadata;
use Tavaan\Testing\TestCase;

/**
 * تست واحد دامنهٔ وبلاگ — Post aggregate (انتقال‌ها/رویدادها/invariantها).
 */
final class PostEntityTest extends TestCase
{
    private function post(ContentStatus $status = ContentStatus::Draft): Post
    {
        $post = Post::draft(
            slug: 'test-post',
            title: 'عنوان آزمایشی',
            authorId: 7,
            excerpt: 'خلاصه',
            body: '<p>بدنه</p>',
            categoryId: 3,
            seo: SeoMetadata::make(title: 'سئو', plainBody: 'خلاصه'),
        );

        if ($status === ContentStatus::Published) {
            $post->publish(new \DateTimeImmutable('2026-10-01 10:00:00', new \DateTimeZone('UTC')));
            $post->pullEvents();
        } elseif ($status === ContentStatus::Scheduled) {
            $post->schedule(new \DateTimeImmutable('2026-11-01 10:00:00', new \DateTimeZone('UTC')));
            $post->pullEvents();
        }

        return $post;
    }

    public function testDraft_UlidAndDefaults(): void
    {
        $post = $this->post();
        self::assertSame(26, \strlen($post->id()));
        self::assertSame(ContentStatus::Draft, $post->status());
        self::assertSame(0, $post->viewCount());
        self::assertNull($post->publishedAt());
        self::assertFalse($post->isPublished());
        self::assertFalse($post->commentsOpen());      // draft → نظرات بسته
    }

    public function testPublish_BackdatesAndRecordsEvent(): void
    {
        $post = $this->post();
        $at = new \DateTimeImmutable('2024-10-01 10:00:00', new \DateTimeZone('UTC'));
        $post->publish($at);

        self::assertSame(ContentStatus::Published, $post->status());
        self::assertSame('2024-10-01 10:00:00', $post->publishedAt()?->format('Y-m-d H:i:s'));
        self::assertTrue($post->isPublished());
        self::assertTrue($post->commentsOpen());

        $events = $post->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PostWasPublished::class, $events[0]);
        self::assertSame('blog.post.published', $events[0]->eventName());
        self::assertSame($post->id(), $events[0]->toPayload()['post_id']);
        self::assertSame([], $post->pullEvents(), 'رویدادها یک‌بار مصرف‌اند');
    }

    public function testPublish_WithoutAtKeepsExistingPublishedAt(): void
    {
        $post = $this->post(ContentStatus::Scheduled);
        $scheduled = $post->publishedAt();
        $post->publish();
        self::assertSame($scheduled?->getTimestamp(), $post->publishedAt()?->getTimestamp());
    }

    public function testSchedule_TransitionAndDueCheck(): void
    {
        $post = $this->post();
        $post->schedule(new \DateTimeImmutable('2026-11-01 10:00:00', new \DateTimeZone('UTC')));

        self::assertSame(ContentStatus::Scheduled, $post->status());
        self::assertFalse($post->isDueForPublishing(new \DateTimeImmutable('2026-10-01 00:00:00', new \DateTimeZone('UTC'))));
        self::assertTrue($post->isDueForPublishing(new \DateTimeImmutable('2026-11-02 00:00:00', new \DateTimeZone('UTC'))));
    }

    public function testInvalidTransitionThrows(): void
    {
        $post = $this->post(ContentStatus::Published);
        $post->archive();
        $post->pullEvents();

        $this->expectException(\DomainException::class);
        $post->publish();   // archived → published مجاز نیست (باید به draft برگردد)
    }

    public function testRevise_RecordsUpdateEvent(): void
    {
        $post = $this->post(ContentStatus::Published);
        $post->revise(title: 'عنوان جدید', excerpt: null, body: '<p>جدید</p>', categoryId: null);

        self::assertSame('عنوان جدید', $post->title());
        self::assertNull($post->categoryId());

        $events = $post->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PostWasUpdated::class, $events[0]);
        self::assertSame('published', $events[0]->toPayload()['status']);
    }

    public function testSoftDelete_HidesAndBlocksComments(): void
    {
        $post = $this->post(ContentStatus::Published);
        $post->softDelete();

        self::assertTrue($post->isTrashed());
        self::assertFalse($post->isPublished());
        self::assertFalse($post->commentsOpen());

        $post->restore();
        self::assertFalse($post->isTrashed());
        self::assertTrue($post->isPublished());
    }

    public function testViewCountIncrement(): void
    {
        $post = $this->post();
        $post->incrementViews();
        $post->incrementViews(4);
        self::assertSame(5, $post->viewCount());
    }

    public function testRowRoundTrip(): void
    {
        $post = $this->post(ContentStatus::Published);
        $row = $post->toRow();
        $rebuilt = Post::fromRow($row);

        self::assertSame($post->id(), $rebuilt->id());
        self::assertSame($post->slug(), $rebuilt->slug());
        self::assertSame($post->title(), $rebuilt->title());
        self::assertSame(ContentStatus::Published, $rebuilt->status());
        self::assertSame(7, $rebuilt->authorId());
        self::assertSame(3, $rebuilt->categoryId());
        self::assertSame($post->publishedAt()?->format('Y-m-d H:i:s'), $rebuilt->publishedAt()?->format('Y-m-d H:i:s'));
        self::assertSame('سئو', $rebuilt->seo()->title);
    }

    public function testCommentableAndMediaContracts(): void
    {
        $post = $this->post();
        self::assertSame('blog_post', $post->commentableType());
        self::assertSame($post->id(), $post->commentableId());
        self::assertSame('blog_post', $post->mediaSubjectType());
        self::assertSame($post->id(), $post->mediaSubjectId());
    }

    public function testChangeSlug(): void
    {
        $post = $this->post();
        $post->changeSlug('slug-jadid');
        self::assertSame('slug-jadid', $post->slug());

        $this->expectException(\DomainException::class);
        $post->changeSlug('  ');
    }

    public function testCategoryEntity(): void
    {
        $category = Category::create('آموزش', 'amoozesh', 'توضیح');
        self::assertNull($category->id());

        $row = $category->toRow();
        self::assertSame('amoozesh', $row['slug']);

        $fromRow = Category::fromRow(['id' => 5, 'name' => 'آموزش', 'slug' => 'amoozesh', 'description' => null]);
        self::assertSame(5, $fromRow->id());

        $fromRow->rename('دوره‌ها');
        self::assertSame('دوره‌ها', $fromRow->name());
    }
}

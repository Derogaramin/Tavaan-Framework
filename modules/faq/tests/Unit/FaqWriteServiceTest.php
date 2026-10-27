<?php

declare(strict_types=1);

namespace Modules\Faq\Tests\Unit;

use Modules\Content\Domain\Enums\ContentStatus;
use Modules\Faq\Application\UseCases\FaqWriteService;
use Modules\Faq\Domain\Entities\FaqCategory;
use Modules\Faq\Domain\Entities\FaqItem;
use Modules\Faq\Domain\Repositories\FaqCategoryRepositoryInterface;
use Modules\Faq\Domain\Repositories\FaqItemRepositoryInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\TestCase;

/**
 * تست FaqWriteService — ساخت/ویرایش دسته و پرسش، اسلاگ یونیک،
 * رندر markdown + پاک‌سازی پاسخ، چرخهٔ وضعیت، trash/force و رأی مفید.
 */
final class FaqWriteServiceTest extends TestCase
{
    use RefreshDatabase;

    private \Tavaan\Core\Application\Application $app;

    private FaqWriteService $service;

    private FaqCategoryRepositoryInterface $categories;

    private FaqItemRepositoryInterface $items;

    protected function setUp(): void
    {
        $this->app = \tavaan_test_app();
        $this->setUpDatabase();

        $config = $this->app->make('config');
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }
        $config->set('cache.default', 'array');

        $this->categories = $this->app->make(FaqCategoryRepositoryInterface::class);
        $this->items = $this->app->make(FaqItemRepositoryInterface::class);
        $this->service = $this->app->make(FaqWriteService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function input(array $overrides = []): array
    {
        return [
            'question' => 'چگونه حساب کاربری بسازم؟',
            'answer' => 'از طریق صفحهٔ **ثبت‌نام** اقدام کنید.',
            ...$overrides,
        ];
    }

    // ─── دسته‌ها ──────────────────────────────────────────────────────────

    public function testCreateCategoryGeneratesSlugFromTitle(): void
    {
        $category = $this->service->createCategory(['title' => 'شروع کار']);

        self::assertSame(26, \strlen($category->id()));
        self::assertNotSame('', $category->slug());
        self::assertSame('شروع کار', $category->title());

        $stored = $this->categories->findBySlug($category->slug());
        self::assertNotNull($stored);
        self::assertSame($category->id(), $stored->id());
    }

    public function testDuplicateCategoryTitleGetsUniqueSlug(): void
    {
        $first = $this->service->createCategory(['title' => 'عمومی']);
        $second = $this->service->createCategory(['title' => 'عمومی']);

        self::assertNotSame($first->slug(), $second->slug());
    }

    public function testCreateCategoryRequiresTitle(): void
    {
        try {
            $this->service->createCategory(['title' => '  ']);
            self::fail('باید ValidationException می‌انداخت');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('title', $e->errors);
        }
    }

    public function testUpdateCategoryRejectsTakenSlug(): void
    {
        $this->service->createCategory(['title' => 'الف', 'slug' => 'alef']);
        $second = $this->service->createCategory(['title' => 'ب', 'slug' => 'be']);

        try {
            $this->service->updateCategory($second, ['title' => 'ب', 'slug' => 'alef']);
            self::fail('اسلاگ تکراری باید رد می‌شد');
        } catch (ValidationException $e) {
            self::assertSame('faq::messages.category_slug_taken', $e->messageId);
        }
    }

    public function testTrashCategoryHidesFromAll(): void
    {
        $category = $this->service->createCategory(['title' => 'حذف‌شدنی']);
        $this->service->trashCategory($category);

        $ids = \array_map(static fn (FaqCategory $c): string => $c->id(), $this->categories->all());
        self::assertNotContains($category->id(), $ids);
        self::assertNull($this->categories->findBySlug($category->slug()));
    }

    // ─── پرسش‌ها ──────────────────────────────────────────────────────────

    public function testCreateStoresDraftWithRenderedSanitizedAnswer(): void
    {
        $item = $this->service->create($this->input(), authorId: 5);

        self::assertSame(ContentStatus::Draft, $item->status());
        self::assertSame(5, $item->authorId());
        self::assertStringContainsString('<strong>', $item->answer());

        $stored = $this->items->findById($item->id());
        self::assertNotNull($stored);
        self::assertSame($item->question(), $stored->question());
        self::assertSame($item->answer(), $stored->answer());
    }

    public function testCreateWithStatusPublishedPublishesImmediately(): void
    {
        $item = $this->service->create($this->input(status: 'published'), authorId: 1);

        self::assertSame(ContentStatus::Published, $item->status());
        self::assertTrue($item->isVisible());
        self::assertSame(1, $this->items->countPublished());
    }

    public function testCreateRequiresQuestionAndAnswer(): void
    {
        try {
            $this->service->create($this->input(question: ''), authorId: 1);
            self::fail('باید ValidationException می‌انداخت');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('question', $e->errors);
        }

        try {
            $this->service->create($this->input(answer: ''), authorId: 1);
            self::fail('باید ValidationException می‌انداخت');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('answer', $e->errors);
        }
    }

    public function testCreateStripsScriptFromAnswer(): void
    {
        $item = $this->service->create($this->input(
            answer: 'پاسخ سالم <script>alert(1)</script> ادامهٔ پاسخ',
        ), authorId: 1);

        self::assertStringNotContainsString('<script', $item->answer());
    }

    public function testUnknownCategoryRejected(): void
    {
        try {
            $this->service->create($this->input(category_id: \str_repeat('0', 26)), authorId: 1);
            self::fail('دستهٔ ناموجود باید رد می‌شد');
        } catch (ValidationException $e) {
            self::assertSame('faq::messages.category_not_found', $e->messageId);
        }
    }

    public function testInvalidStatusRejected(): void
    {
        try {
            $this->service->create($this->input(status: 'archived'), authorId: 1);
            self::fail('وضعیت نامعتبر باید رد می‌شد');
        } catch (ValidationException $e) {
            self::assertSame('faq::messages.status_invalid', $e->messageId);
        }
    }

    public function testUpdateChangesQuestionAnswerAndCategory(): void
    {
        $category = $this->service->createCategory(['title' => 'پرداخت']);
        $item = $this->service->create($this->input(), authorId: 9);

        $updated = $this->service->update($item, $this->input(
            question: 'چگونه پرداخت کنم؟',
            answer: 'با کارت بانکی.',
            category_id: $category->id(),
            sort_order: '4',
        ));

        self::assertSame('چگونه پرداخت کنم؟', $updated->question());
        self::assertSame($category->id(), $updated->categoryId());
        self::assertSame(4, $updated->sortOrder());
        self::assertSame(9, $updated->authorId());

        $stored = $this->items->findById($item->id());
        self::assertNotNull($stored);
        self::assertSame('چگونه پرداخت کنم؟', $stored->question());
    }

    public function testStatusLifecyclePersisted(): void
    {
        $item = $this->service->create($this->input(), authorId: 1);

        $this->service->publish($item);
        self::assertSame('published', $this->items->findById($item->id())?->status()->value);

        $this->service->archive($item);
        self::assertSame('archived', $this->items->findById($item->id())?->status()->value);

        $this->service->backToDraft($item);
        self::assertSame('draft', $this->items->findById($item->id())?->status()->value);
    }

    public function testTrashSoftDeletesAndForceDeleteRemoves(): void
    {
        $item = $this->service->create($this->input(status: 'published'), authorId: 1);

        $this->service->trash($item);
        $trashed = $this->items->findById($item->id());
        self::assertNotNull($trashed);
        self::assertTrue($trashed->isTrashed());
        self::assertSame(0, $this->items->countPublished());

        $this->service->forceDelete($trashed);
        self::assertNull($this->items->findById($item->id()));
    }

    public function testForceDeleteOnLiveItemThrows(): void
    {
        $item = $this->service->create($this->input(), authorId: 1);

        $this->expectException(\DomainException::class);
        $this->service->forceDelete($item);
    }

    public function testRegisterHelpfulIncrementsPersistedCounter(): void
    {
        $item = $this->service->create($this->input(status: 'published'), authorId: 1);

        $updated = $this->service->registerHelpful($item, true);
        self::assertSame(1, $updated->helpfulYes());
        self::assertSame(0, $updated->helpfulNo());

        $stored = $this->items->findById($item->id());
        self::assertNotNull($stored);
        self::assertSame(1, $stored->helpfulYes());

        $this->service->registerHelpful($stored, false);
        $after = $this->items->findById($item->id());
        self::assertNotNull($after);
        self::assertSame(1, $after->helpfulYes());
        self::assertSame(1, $after->helpfulNo());
    }

    // ─── خواندن عمومی ─────────────────────────────────────────────────────

    public function testPublishedAllSortedAndTrashedHidden(): void
    {
        $this->service->create($this->input(question: 'پرسش یک؟', sort_order: '2', status: 'published'), authorId: 1);
        $this->service->create($this->input(question: 'پرسش دو؟', sort_order: '1', status: 'published'), authorId: 1);
        $hidden = $this->service->create($this->input(question: 'پرسش سه؟', status: 'published'), authorId: 1);
        $this->service->trash($hidden);

        $rows = $this->items->publishedAll();
        $questions = \array_map(static fn (FaqItem $i): string => $i->question(), $rows);

        self::assertSame(['پرسش دو؟', 'پرسش یک؟'], $questions);
    }
}

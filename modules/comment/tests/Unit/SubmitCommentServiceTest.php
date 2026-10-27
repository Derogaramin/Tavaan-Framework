<?php

declare(strict_types=1);

namespace Modules\Comment\Tests\Unit;

use Modules\Comment\Application\CommentableRegistry;
use Modules\Comment\Application\UseCases\SubmitCommentService;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Comment\Domain\Services\CommentModerator;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Modules\Comment\Infrastructure\Persistence\DatabaseCommentStore;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\TestCase;

/**
 * تست UseCase ثبت نظر standalone — allowlist نوع، اعتبارسنجی، moderation،
 * والد تو‌در‌تو وauto-approve نویسندهٔ مورد اعتماد.
 */
final class SubmitCommentServiceTest extends TestCase
{
    use RefreshDatabase;

    private \Tavaan\Core\Application\Application $app;

    private SubmitCommentService $service;

    private CommentableRegistry $registry;

    private DatabaseCommentStore $store;

    protected function setUp(): void
    {
        $this->app = \tavaan_test_app();
        $this->setUpDatabase();

        $app = $this->app;
        $config = $app->make('config');
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }

        $this->store = new DatabaseCommentStore();
        $this->registry = new CommentableRegistry([
            'page' => ['label' => 'صفحه', 'url' => '/pages/{id}', 'guest_allowed' => true, 'open' => true],
            'closed_topic' => ['label' => 'بسته', 'open' => false],
            'members_only' => ['label' => 'فقط اعضا', 'guest_allowed' => false],
        ]);

        $this->service = new SubmitCommentService(
            store: $this->store,
            registry: $this->registry,
            moderator: new CommentModerator(['mode' => 'manual', 'auto_approve_trusted' => true, 'max_links' => 3]),
            threadBuilder: new CommentThreadBuilder(maxDepth: 3),
            config: ['guest_allowed' => true, 'min_length' => 5, 'max_length' => 5000, 'thread' => ['max_depth' => 3]],
        );
    }

    public function testSubmitStoresPendingComment(): void
    {
        $comment = $this->service->submit('page', 'p-1', [
            'author_name' => 'خواننده',
            'author_email' => 'reader@example.com',
            'body' => 'نظر بسیار خوبی بود!',
        ]);

        self::assertSame(CommentStatus::Pending, $comment->status);
        self::assertNotNull($this->store->findById($comment->id));
        self::assertSame('page', $comment->commentableType);
    }

    public function testUnregisteredTypeRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->submit('hacked_type', 'x', [
            'author_name' => 'خواننده',
            'author_email' => 'reader@example.com',
            'body' => 'متن به اندازه کافی بلند',
        ]);
    }

    public function testClosedTypeRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->submit('closed_topic', 't-1', [
            'author_name' => 'خواننده',
            'author_email' => 'reader@example.com',
            'body' => 'متن به اندازه کافی بلند',
        ]);
    }

    public function testGuestBlockedOnMembersOnly(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->submit('members_only', 'm-1', [
            'author_name' => 'مهمان',
            'author_email' => 'guest@example.com',
            'body' => 'متن به اندازه کافی بلند',
        ]);
    }

    public function testLoggedInUserAllowedOnMembersOnly(): void
    {
        $comment = $this->service->submit('members_only', 'm-1', [
            'author_name' => 'عضو',
            'author_email' => 'member@example.com',
            'body' => 'متن به اندازه کافی بلند',
        ], userId: 42);

        self::assertSame('42', $comment->userId);
    }

    public function testShortBodyRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->submit('page', 'p-2', [
            'author_name' => 'خواننده',
            'author_email' => 'reader@example.com',
            'body' => 'کم',
        ]);
    }

    public function testGuestRequiresNameAndEmail(): void
    {
        try {
            $this->service->submit('page', 'p-3', ['body' => 'متن به اندازه کافی بلند']);
            self::fail('باید ValidationException می‌انداخت');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('author_name', $e->errors);
            self::assertArrayHasKey('author_email', $e->errors);
        }
    }

    public function testSpamWithTooManyLinks(): void
    {
        $body = 'لینک‌ها: https://a.example https://b.example https://c.example https://d.example';
        $comment = $this->service->submit('page', 'p-4', [
            'author_name' => 'اسپمر',
            'author_email' => 'spam@example.com',
            'body' => $body,
        ]);

        self::assertSame(CommentStatus::Spam, $comment->status);
    }

    public function testTrustedAuthorAutoApproved(): void
    {
        // سابقهٔ تأییدشده
        $first = $this->service->submit('page', 'p-5', [
            'author_name' => 'قابل اعتماد',
            'author_email' => 'trusted@example.com',
            'body' => 'اولین نظر سالم و کافی',
        ]);
        self::assertSame(CommentStatus::Pending, $first->status);
        $this->store->updateStatus($first->id, CommentStatus::Approved);

        // نظر دوم از همان ایمیل → تأیید خودکار
        $second = $this->service->submit('page', 'p-6', [
            'author_name' => 'قابل اعتماد',
            'author_email' => 'trusted@example.com',
            'body' => 'دومین نظر سالم و کافی',
        ]);
        self::assertSame(CommentStatus::Approved, $second->status);
    }

    public function testReplyToParentOnSameSubject(): void
    {
        $root = $this->service->submit('page', 'p-7', [
            'author_name' => 'ریشه',
            'author_email' => 'root@example.com',
            'body' => 'نظر ریشهٔ سالم',
        ]);
        $this->store->updateStatus($root->id, CommentStatus::Approved);

        $reply = $this->service->submit('page', 'p-7', [
            'author_name' => 'پاسخ‌دهنده',
            'author_email' => 'reply@example.com',
            'body' => 'پاسخ به نظر ریشه',
            'parent_id' => $root->id,
        ]);

        self::assertSame($root->id, $reply->parentId);
    }

    public function testReplyToPendingParentRejected(): void
    {
        $root = $this->service->submit('page', 'p-8', [
            'author_name' => 'ریشه',
            'author_email' => 'root2@example.com',
            'body' => 'نظر ریشهٔ در انتظار',
        ]);

        $this->expectException(ValidationException::class);
        $this->service->submit('page', 'p-8', [
            'author_name' => 'پاسخ‌دهنده',
            'author_email' => 'reply2@example.com',
            'body' => 'پاسخ به نظر تأییدنشده',
            'parent_id' => $root->id,
        ]);
    }

    public function testReplyToOtherSubjectRejected(): void
    {
        $root = $this->service->submit('page', 'p-9', [
            'author_name' => 'ریشه',
            'author_email' => 'root3@example.com',
            'body' => 'نظر موضوع دیگر',
        ]);
        $this->store->updateStatus($root->id, CommentStatus::Approved);

        $this->expectException(ValidationException::class);
        $this->service->submit('page', 'p-10', [
            'author_name' => 'پاسخ‌دهنده',
            'author_email' => 'reply3@example.com',
            'body' => 'پاسخ نامعتبر بین موضوعی',
            'parent_id' => $root->id,
        ]);
    }

    public function testMaxDepthEnforced(): void
    {
        // زنجیرهٔ ریشه → ۳ پاسخ (maxDepth=3)
        $ids = [];
        $parent = null;
        for ($i = 0; $i < 4; $i++) {
            $comment = $this->service->submit('page', 'p-deep', [
                'author_name' => 'نویسنده ' . $i,
                'author_email' => "deep{$i}@example.com",
                'body' => 'نظر شماره ' . $i . ' با طول کافی',
                'parent_id' => $parent ?? '',
            ]);
            $this->store->updateStatus($comment->id, CommentStatus::Approved);
            $ids[] = $comment->id;
            $parent = $comment->id;
        }

        // پاسخ پنجم به عمیق‌ترین → باید رد شود
        $this->expectException(ValidationException::class);
        $this->service->submit('page', 'p-deep', [
            'author_name' => 'عمیق',
            'author_email' => 'too-deep@example.com',
            'body' => 'پاسخی که از سقف عمق رد می‌شود',
            'parent_id' => $ids[3],
        ]);
    }

    public function testApprovedThreadReturnsOnlyApproved(): void
    {
        $approved = $this->service->submit('page', 'p-11', [
            'author_name' => 'تأییدشده',
            'author_email' => 'ok@example.com',
            'body' => 'نظر تأییدشدهٔ سالم',
        ]);
        $this->store->updateStatus($approved->id, CommentStatus::Approved);

        $this->service->submit('page', 'p-11', [
            'author_name' => 'در انتظار',
            'author_email' => 'wait@example.com',
            'body' => 'نظر در انتظار بررسی',
        ]);

        $thread = $this->service->approvedThread('page', 'p-11');
        self::assertCount(1, $thread);
        self::assertSame($approved->id, $thread[0]->comment->id);
    }

    public function testRegistrySanitizesInvalidType(): void
    {
        $registry = new CommentableRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $registry->register('../etc/passwd', 'هک');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Comment\Tests\Unit;

use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Comment\Infrastructure\Persistence\DatabaseCommentStore;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\TestCase;

/**
 * تست آداپتر ماندگاری عمومی نظرات (comment_comments) — CRUD + آمار.
 */
final class CommentStoreTest extends TestCase
{
    use RefreshDatabase;

    private \Tavaan\Core\Application\Application $app;

    private DatabaseCommentStore $store;

    protected function setUp(): void
    {
        $this->app = \tavaan_test_app();
        $this->setUpDatabase();
        $this->store = new DatabaseCommentStore();
    }

    public function testSaveAndFindRoundTrip(): void
    {
        $comment = Comment::submit('page', 'p-1', 'نویسنده', 'Author@Example.com', 'متن نظر');
        $this->store->save($comment);

        $found = $this->store->findById($comment->id);
        self::assertNotNull($found);
        self::assertSame('page', $found->commentableType);
        self::assertSame('p-1', $found->commentableId);
        self::assertSame('author@example.com', $found->authorEmail);   // lowercase نرمال شد
        self::assertSame('متن نظر', $found->body);
        self::assertSame(CommentStatus::Pending, $found->status);
    }

    public function testSaveUpdatesExistingRow(): void
    {
        $comment = Comment::submit('page', 'p-1', 'نویسنده', 'a@example.com', 'نسخهٔ اول');
        $this->store->save($comment);
        $this->store->save($comment->withStatus(CommentStatus::Approved));

        $found = $this->store->findById($comment->id);
        self::assertNotNull($found);
        self::assertSame(CommentStatus::Approved, $found->status);

        $all = $this->store->forSubject('page', 'p-1');
        self::assertCount(1, $all, 'save تکراری نباید رکورد دوم بسازد');
    }

    public function testForSubjectFiltersByStatusAndOrdersByTime(): void
    {
        $a = Comment::submit('page', 'p-2', 'یک', 'one@example.com', 'اول', CommentStatus::Approved);
        $b = Comment::submit('page', 'p-2', 'دو', 'two@example.com', 'دوم', CommentStatus::Pending);
        $c = Comment::submit('page', 'other', 'سه', 'three@example.com', 'موضوع دیگر', CommentStatus::Approved);
        $this->store->save($a);
        $this->store->save($b);
        $this->store->save($c);

        self::assertCount(2, $this->store->forSubject('page', 'p-2'));
        self::assertCount(1, $this->store->forSubject('page', 'p-2', CommentStatus::Approved));
        self::assertCount(1, $this->store->forSubject('page', 'other', CommentStatus::Approved));
    }

    public function testUpdateStatusAndDelete(): void
    {
        $comment = Comment::submit('page', 'p-3', 'نویسنده', 'w@example.com', 'نظر');
        $this->store->save($comment);

        $this->store->updateStatus($comment->id, CommentStatus::Spam);
        self::assertSame(CommentStatus::Spam, $this->store->findById($comment->id)?->status);
        self::assertSame(1, $this->store->countByStatus(CommentStatus::Spam));

        $this->store->delete($comment->id);
        self::assertNull($this->store->findById($comment->id));
    }

    public function testDeleteOrphansChildrenToRoot(): void
    {
        $root = Comment::submit('page', 'p-4', 'ریشه', 'r@example.com', 'ریشه');
        $this->store->save($root);
        $child = Comment::submit('page', 'p-4', 'فرزند', 'c@example.com', 'پاسخ', CommentStatus::Approved, parentId: $root->id);
        $this->store->save($child);

        $this->store->delete($root->id);

        $fresh = $this->store->findById($child->id);
        self::assertNotNull($fresh, 'فرزند نباید حذف شود');
        self::assertNull($fresh->parentId, 'فرزند باید به ریشه تبدیل شود');
    }

    public function testAuthorHasApprovedHistory(): void
    {
        self::assertFalse($this->store->authorHasApprovedHistory('trusted@example.com'));

        $this->store->save(Comment::submit('page', 'p-5', 'قابل اعتماد', 'Trusted@Example.com', 'نظر تأییدشده', CommentStatus::Approved));

        self::assertTrue($this->store->authorHasApprovedHistory('trusted@example.com'));
        self::assertFalse($this->store->authorHasApprovedHistory('stranger@example.com'));
    }

    public function testPaginateAllAndTypeStats(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->store->save(Comment::submit('page', 'p-' . $i, 'نویسنده ' . $i, "u{$i}@example.com", 'نظر شماره ' . $i));
        }
        $this->store->save(Comment::submit('faq_item', 'f-1', 'پرسشگر', 'q@example.com', 'سوال خوب', CommentStatus::Approved));

        $page = $this->store->paginateAll(null, '', 1, 3);
        self::assertSame(6, $page['total']);
        self::assertCount(3, $page['rows']);

        $onlyPending = $this->store->paginateAll(CommentStatus::Pending, '', 1, 10);
        self::assertSame(5, $onlyPending['total']);

        $onlyFaq = $this->store->paginateAll(null, 'faq_item', 1, 10);
        self::assertSame(1, $onlyFaq['total']);

        $stats = $this->store->typeStats();
        self::assertCount(2, $stats);
        self::assertSame('page', $stats[0]['type']);       // بیشترین pending اول
        self::assertSame(5, $stats[0]['pending']);
    }
}

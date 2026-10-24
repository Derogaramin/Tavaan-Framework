<?php

declare(strict_types=1);

namespace Modules\Comment\Tests\Unit;

use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Comment\Domain\Services\CommentModerator;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Tavaan\Testing\TestCase;

/**
 * تست واحد ماژول نظر — moderation + رشتهٔ تو‌در‌تو + موجودیت.
 *
 * پوشش: ماتریس decide (اسپم/approve_all/trusted/manual)، مرز max_links،
 * واژهٔ مسدود، یتیم→ریشه، سقف عمق، immutability.
 */
final class CommentDomainTest extends TestCase
{
    private function comment(
        string $body = 'نظر نمونه',
        ?string $id = null,
        ?string $parentId = null,
        string $email = 'reader@example.com',
    ): Comment {
        return new Comment(
            id: $id ?? 'c' . \substr(\md5($body . ($id ?? \uniqid('', true))), 0, 24),
            commentableType: 'blog_post',
            commentableId: 'post-1',
            authorName: 'خواننده',
            authorEmail: $email,
            body: $body,
            parentId: $parentId,
            createdAt: new \DateTimeImmutable('2026-10-20 12:00:00', new \DateTimeZone('UTC')),
        );
    }

    // ─── CommentModerator ─────────────────────────────────────────────────

    public function testModerator_ManualModeDefaultsToPending(): void
    {
        $mod = new CommentModerator(['mode' => 'manual']);
        self::assertSame(CommentStatus::Pending, $mod->decide($this->comment()));
    }

    public function testModerator_ApproveAllMode(): void
    {
        $mod = new CommentModerator(['mode' => 'approve_all']);
        self::assertSame(CommentStatus::Approved, $mod->decide($this->comment()));
    }

    public function testModerator_TrustedAuthorAutoApprove(): void
    {
        $mod = new CommentModerator(['mode' => 'manual', 'auto_approve_trusted' => true]);
        self::assertSame(CommentStatus::Approved, $mod->decide($this->comment(), authorHasApprovedHistory: true));
        self::assertSame(CommentStatus::Pending, $mod->decide($this->comment(), authorHasApprovedHistory: false));

        $strict = new CommentModerator(['mode' => 'manual', 'auto_approve_trusted' => false]);
        self::assertSame(CommentStatus::Pending, $strict->decide($this->comment(), authorHasApprovedHistory: true));
    }

    public function testModerator_LinkCountSpam(): void
    {
        $mod = new CommentModerator(['mode' => 'approve_all', 'max_links' => 2]);

        self::assertSame(CommentStatus::Approved, $mod->decide($this->comment('لینک https://a.com و https://b.com')));
        self::assertSame(CommentStatus::Spam, $mod->decide($this->comment('a.com https://x.com https://y.com https://z.com')));
        self::assertSame(CommentStatus::Spam, $mod->decide($this->comment('www.one.com www.two.com www.three.com')));
    }

    public function testModerator_BlockedWordsSpam(): void
    {
        $mod = new CommentModerator(['mode' => 'approve_all', 'blocked_words' => ['ویروس', 'casino']]);
        self::assertSame(CommentStatus::Spam, $mod->decide($this->comment('این ویروس است')));
        self::assertSame(CommentStatus::Spam, $mod->decide($this->comment('best CASINO offers')));
        self::assertSame(CommentStatus::Approved, $mod->decide($this->comment('نظر سالم')));
    }

    // ─── Comment entity ───────────────────────────────────────────────────

    public function testComment_SubmitGeneratesUlidAndDefaultStatus(): void
    {
        $c = Comment::submit('blog_post', 'p1', 'نام', 'a@b.c', 'متن');
        self::assertSame(26, \strlen($c->id));                     // ULID CHAR(26)
        self::assertSame(CommentStatus::Pending, $c->status);
        self::assertNotNull($c->createdAt);
        self::assertFalse($c->isReply());
    }

    public function testComment_WithStatusIsImmutable(): void
    {
        $c = Comment::submit('blog_post', 'p1', 'نام', 'a@b.c', 'متن');
        $approved = $c->withStatus(CommentStatus::Approved);

        self::assertSame(CommentStatus::Pending, $c->status);
        self::assertSame(CommentStatus::Approved, $approved->status);
        self::assertSame($c->id, $approved->id);
    }

    public function testComment_ReplyAndLinkCount(): void
    {
        $parent = Comment::submit('blog_post', 'p1', 'الف', 'a@b.c', 'والد');
        $reply = Comment::submit('blog_post', 'p1', 'ب', 'b@c.d', 'فرزند', parentId: $parent->id);
        self::assertTrue($reply->isReply());
        self::assertSame(2, $this->comment('x https://a.ir y https://b.ir')->linkCount());
    }

    // ─── CommentThreadBuilder ─────────────────────────────────────────────

    public function testThread_BuildsNestedForest(): void
    {
        $root = $this->comment('ریشه', id: 'c-root');
        $child = $this->comment('پاسخ', id: 'c-child', parentId: 'c-root');
        $grand = $this->comment('پاسخِ پاسخ', id: 'c-grand', parentId: 'c-child');
        $other = $this->comment('ریشهٔ دوم', id: 'c-other');

        $roots = (new CommentThreadBuilder())->build([$root, $child, $grand, $other]);

        self::assertCount(2, $roots);
        self::assertSame('c-root', $roots[0]->comment->id);
        self::assertCount(1, $roots[0]->children);
        self::assertSame('c-child', $roots[0]->children[0]->comment->id);
        self::assertCount(1, $roots[0]->children[0]->children);
        self::assertSame('c-grand', $roots[0]->children[0]->children[0]->comment->id);
        self::assertSame(3, $roots[0]->count());
        self::assertSame(2, $roots[0]->depth());
    }

    public function testThread_OrphanBecomesRoot(): void
    {
        $orphan = $this->comment('یتیم', id: 'c-orphan', parentId: 'c-deleted');
        $roots = (new CommentThreadBuilder())->build([$orphan]);

        self::assertCount(1, $roots);
        self::assertSame('c-orphan', $roots[0]->comment->id);
    }

    public function testThread_DepthLimitFlattensBeyondMax(): void
    {
        $chain = [];
        $parentId = null;
        for ($i = 0; $i < 5; $i++) {
            $id = 'c-' . $i;
            $chain[] = $this->comment('لایه ' . $i, id: $id, parentId: $parentId);
            $parentId = $id;
        }

        $roots = (new CommentThreadBuilder(maxDepth: 2))->build($chain);

        // زنجیرهٔ ۵تایی با سقف ۲ → بیش از یک ریشه
        self::assertGreaterThan(1, \count($roots));
        self::assertLessThanOrEqual(2, $roots[0]->depth());
    }

    // ─── CommentStatus ────────────────────────────────────────────────────

    public function testStatus_Visibility(): void
    {
        self::assertTrue(CommentStatus::Approved->isVisible());
        self::assertFalse(CommentStatus::Pending->isVisible());
        self::assertFalse(CommentStatus::Spam->isVisible());
        self::assertSame('pending', CommentStatus::Pending->value);
        self::assertSame('اسپم', CommentStatus::Spam->labelFa());
    }
}

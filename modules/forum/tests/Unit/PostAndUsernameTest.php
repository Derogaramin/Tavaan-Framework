<?php

declare(strict_types=1);

namespace Modules\Forum\Tests\Unit;

use Modules\Forum\Domain\Entities\Post;
use Modules\Forum\Domain\Enums\PostStatus;
use Modules\Forum\Domain\ValueObjects\Username;
use Tavaan\Testing\TestCase;

/**
 * تست Username VO و موجودیت Post — invariantهای DOC-094 §1/§2.
 */
final class PostAndUsernameTest extends TestCase
{
    // ─── Username ─────────────────────────────────────────────────────────

    public function testValidUsernames(): void
    {
        self::assertTrue(Username::isValid('ali_reza'));
        self::assertTrue(Username::isValid('نگار'));
        self::assertTrue(Username::isValid('user.name1'));
        self::assertTrue(Username::isValid('abc'));
    }

    public function testInvalidUsernames(): void
    {
        self::assertFalse(Username::isValid('ab'));          // کوتاه
        self::assertFalse(Username::isValid('1user'));       // شروع با رقم
        self::assertFalse(Username::isValid('_user'));       // شروع با _
        self::assertFalse(Username::isValid('us  er'));      // فاصله
        self::assertFalse(Username::isValid('user__name'));  // دو زیرخط متوالی
        self::assertFalse(Username::isValid(\str_repeat('a', 31)));  // بلند
    }

    public function testSuggestGeneratesUniqueUsername(): void
    {
        $taken = ['admin' => true];
        $exists = static fn (string $candidate): bool => isset($taken[$candidate]);

        $first = Username::suggest('admin', $exists, 5);
        self::assertSame('admin_5', $first);   // admin گرفته شده → suffix

        $second = Username::suggest('  کاربر تازه!  ', $exists, 0);
        self::assertSame(1, \preg_match('/^[\p{L}][\p{L}\p{Nd}_]*$/u', $second));
    }

    // ─── Post entity ──────────────────────────────────────────────────────

    public function testHideClearsSolutionAndUnhideRestores(): void
    {
        $post = Post::create('THREADULID0000000000000', 1, '<p>پاسخ</p>');
        $post->markSolution(true);

        $post->hide();
        self::assertSame(PostStatus::Hidden, $post->status());
        self::assertFalse($post->isSolution(), 'پست پنهان نمی‌تواند برگزیده بماند');

        $post->unhide();
        self::assertTrue($post->isVisible());
    }

    public function testSoftDeleteIsOneWay(): void
    {
        $post = Post::create('THREADULID0000000000000', 1, '<p>پاسخ</p>');

        $post->softDelete();
        self::assertSame(PostStatus::Deleted, $post->status());

        $post->unhide();   // بی‌اثر روی deleted
        self::assertSame(PostStatus::Deleted, $post->status());
        self::assertFalse($post->isVisible());
    }

    public function testVoteScoreApplied(): void
    {
        $post = Post::create('THREADULID0000000000000', 1, '<p>پاسخ</p>');
        self::assertSame(0, $post->voteScore());

        $post->applyVoteScore(5);
        self::assertSame(5, $post->voteScore());

        $post->applyVoteScore(-2);
        self::assertSame(-2, $post->voteScore());
    }

    public function testRoundTripToRowFromRow(): void
    {
        $post = Post::create('THREADULID0000000000000', 7, '<p>متن</p>', 'PARENTULID00000000000000');
        $post->markSolution(true);
        $post->applyVoteScore(3);

        $restored = Post::fromRow($post->toRow());

        self::assertSame($post->id(), $restored->id());
        self::assertSame($post->threadId(), $restored->threadId());
        self::assertSame(7, $restored->authorId());
        self::assertSame('PARENTULID00000000000000', $restored->parentId());
        self::assertTrue($restored->isSolution());
        self::assertSame(3, $restored->voteScore());
        self::assertTrue($restored->isVisible());
    }
}

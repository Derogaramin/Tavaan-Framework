<?php

declare(strict_types=1);

namespace Modules\Forum\Tests\Unit;

use Modules\Forum\Domain\Services\MentionExtractor;
use Modules\Forum\Domain\Services\QuoteBuilder;
use Modules\Forum\Domain\Services\ReputationCalculator;
use Modules\Forum\Domain\Services\SpamFilter;
use Tavaan\Testing\TestCase;

/**
 * تست سرویس‌های دامنهٔ خالص انجمن — MentionExtractor/QuoteBuilder/
 * ReputationCalculator/SpamFilter (DOC-094 §6).
 */
final class DomainServicesTest extends TestCase
{
    // ─── MentionExtractor ─────────────────────────────────────────────────

    public function testExtractsLatinAndPersianUsernames(): void
    {
        $extractor = new MentionExtractor();

        $mentions = $extractor->extract('سلام @ali_reza و @نگار عزیز، لطفاً @mod1 را ببینید');

        self::assertContains('ali_reza', $mentions);
        self::assertContains('نگار', $mentions);
        self::assertContains('mod1', $mentions);
    }

    public function testIgnoresMentionsInsideCodeBlocks(): void
    {
        $extractor = new MentionExtractor();

        $mentions = $extractor->extract("مثال:\n```\n@internal_name\n```\nو inline `@other_name` ولی @real_user بیرون است");

        self::assertNotContains('internal_name', $mentions);
        self::assertNotContains('other_name', $mentions);
        self::assertContains('real_user', $mentions);
    }

    public function testDeduplicatesAndRespectsMax(): void
    {
        $extractor = new MentionExtractor(maxPerPost: 2);

        $mentions = $extractor->extract('@alpha @Alpha @beta @gamma @delta');

        self::assertCount(2, $mentions);
        self::assertSame('alpha', \mb_strtolower($mentions[0]));
    }

    public function testShortNamesAreIgnored(): void
    {
        $extractor = new MentionExtractor();

        // کمتر از ۳ نویسه → mention نیست
        self::assertSame([], $extractor->extract('@ab سلام'));
    }

    // ─── QuoteBuilder ─────────────────────────────────────────────────────

    public function testBuildsMarkdownQuoteWithAuthor(): void
    {
        $builder = new QuoteBuilder(maxLength: 800);

        $quote = $builder->build('<p>متن <strong>پست</strong> مبدأ</p>', 'nevisandeh');

        self::assertStringContainsString('> **nevisandeh** نوشت:', $quote);
        self::assertStringContainsString('> متن پست مبدأ', $quote);
        self::assertStringNotContainsString('<strong>', $quote);
    }

    public function testTruncatesLongQuotes(): void
    {
        $builder = new QuoteBuilder(maxLength: 50);

        $quote = $builder->build('<p>' . \str_repeat('طولانی ', 50) . '</p>', 'user');

        self::assertLessThan(220, \mb_strlen($quote));
    }

    // ─── ReputationCalculator ─────────────────────────────────────────────

    public function testKarmaDeltas(): void
    {
        $calc = new ReputationCalculator([
            'upvote_received' => 2,
            'downvote_received' => -1,
            'solution_author' => 15,
            'thread_created' => 1,
            'post_created' => 1,
        ]);

        self::assertSame(2, $calc->forVoteReceived(1));
        self::assertSame(-1, $calc->forVoteReceived(-1));
        self::assertSame(0, $calc->forVoteReceived(0));
        self::assertSame(15, $calc->forSolution(true));
        self::assertSame(-15, $calc->forSolution(false));
        self::assertSame(1, $calc->forThreadCreated());
        self::assertSame(1, $calc->forPostCreated());
    }

    public function testDisabledKarmaIsZero(): void
    {
        $calc = new ReputationCalculator(['enabled' => false]);

        self::assertSame(0, $calc->forVoteReceived(1));
        self::assertSame(0, $calc->forSolution(true));
        self::assertSame(0, $calc->forPostCreated());
    }

    // ─── SpamFilter ───────────────────────────────────────────────────────

    public function testTooManyLinksIsSpam(): void
    {
        $filter = new SpamFilter(['max_links' => 2]);

        $body = '[a](https://x.com) [b](https://y.com) [c](https://z.com)';

        self::assertTrue($filter->isSpam($body));
        self::assertSame(3, $filter->linkCount($body));
    }

    public function testBlockedWordIsSpam(): void
    {
        $filter = new SpamFilter(['blocked_words' => ['قرعه‌کشی']]);

        self::assertTrue($filter->isSpam('برندهٔ قرعه‌کشی بزرگ!'));
        self::assertFalse($filter->isSpam('متن معمولی انجمن'));
    }

    public function testHtmlLinksCounted(): void
    {
        $filter = new SpamFilter(['max_links' => 1]);

        self::assertTrue($filter->isSpam('<a href="https://x">1</a><a href="https://y">2</a>'));
        self::assertFalse($filter->isSpam('<a href="https://x">1</a>'));
    }
}

<?php

declare(strict_types=1);

namespace Modules\Poll\Tests\Unit;

use Modules\Poll\Domain\Entities\Poll;
use Modules\Poll\Domain\Enums\PollStatus;
use Modules\Poll\Domain\Events\PollWasClosed;
use Modules\Poll\Domain\Events\PollWasOpened;
use Modules\Poll\Domain\Services\VoteTally;
use Tavaan\Testing\TestCase;

/**
 * تست دامنهٔ Poll + VoteTally — کارخانه، ماتریس وضعیت، رویدادها،
 * پنجرهٔ زمانی (isVotable بدون cron)، roundtrip و جمع‌بندی خالص.
 */
final class PollDomainTest extends TestCase
{
    /** @param list<string> $options */
    private function makePoll(
        array $options = ['گزینهٔ یک', 'گزینهٔ دو', 'گزینهٔ سه'],
        bool $multiple = false,
        ?\DateTimeImmutable $startsAt = null,
        ?\DateTimeImmutable $endsAt = null,
    ): Poll {
        return Poll::draft(
            slug: 'پرسش-آزمایشی',
            question: 'پرسش آزمایشی؟',
            options: $options,
            authorId: 3,
            multiple: $multiple,
            startsAt: $startsAt,
            endsAt: $endsAt,
        );
    }

    // ─── کارخانه و وضعیت ──────────────────────────────────────────────────

    public function testDraftFactoryDefaults(): void
    {
        $poll = $this->makePoll();

        self::assertSame(26, \strlen($poll->id()));
        self::assertSame(PollStatus::Draft, $poll->status());
        self::assertSame(3, $poll->optionCount());
        self::assertSame(3, $poll->authorId());
        self::assertFalse($poll->multiple());
        self::assertFalse($poll->isTrashed());
        self::assertFalse($poll->isVotable());          // draft رأی‌پذیر نیست
    }

    public function testOpenRecordsEvent(): void
    {
        $poll = $this->makePoll();
        $poll->open();

        self::assertSame(PollStatus::Open, $poll->status());
        self::assertTrue($poll->isVotable());
        self::assertTrue($poll->status()->isPublic());

        $events = $poll->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PollWasOpened::class, $events[0]);
        self::assertSame('poll.opened', $events[0]->eventName());
        self::assertSame($poll->id(), $events[0]->pollId);
        self::assertCount(0, $poll->pullEvents());
    }

    public function testCloseRecordsEventWithVoteTotal(): void
    {
        $poll = $this->makePoll();
        $poll->open();
        $poll->pullEvents();
        $poll->close(17);

        self::assertSame(PollStatus::Closed, $poll->status());
        self::assertFalse($poll->isVotable());
        self::assertTrue($poll->status()->isPublic());   // بسته ولی قابل نمایش (نتایج)

        $events = $poll->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PollWasClosed::class, $events[0]);
        self::assertSame('poll.closed', $events[0]->eventName());
        self::assertSame(17, $events[0]->totalVotes);
    }

    public function testInvalidTransitionsThrow(): void
    {
        $poll = $this->makePoll();

        // draft → draft (بازکردنِ دوبارهٔ پیش‌نویس پس از open)
        $poll->open();
        try {
            $poll->open();
            self::fail('open→open باید DomainException می‌انداخت');
        } catch (\DomainException) {
            self::assertTrue(true);
        }

        // closed → closed
        $poll->close();
        $this->expectException(\DomainException::class);
        $poll->close();
    }

    public function testClosedCanReopen(): void
    {
        $poll = $this->makePoll();
        $poll->open();
        $poll->close();
        $poll->open();

        self::assertSame(PollStatus::Open, $poll->status());
    }

    // ─── پنجرهٔ زمانی ────────────────────────────────────────────────────

    public function testVotableWindow(): void
    {
        $now = new \DateTimeImmutable('2026-10-27 12:00:00', new \DateTimeZone('UTC'));

        $notStarted = $this->makePoll(startsAt: new \DateTimeImmutable('2026-11-01 00:00:00', new \DateTimeZone('UTC')));
        $notStarted->open();
        self::assertFalse($notStarted->isVotable($now));

        $expired = $this->makePoll(endsAt: new \DateTimeImmutable('2026-10-01 00:00:00', new \DateTimeZone('UTC')));
        $expired->open();
        self::assertFalse($expired->isVotable($now));
        self::assertTrue($expired->isExpired($now));

        $inWindow = $this->makePoll(
            startsAt: new \DateTimeImmutable('2026-10-01 00:00:00', new \DateTimeZone('UTC')),
            endsAt: new \DateTimeImmutable('2026-11-01 00:00:00', new \DateTimeZone('UTC')),
        );
        $inWindow->open();
        self::assertTrue($inWindow->isVotable($now));
        self::assertFalse($inWindow->isExpired($now));
    }

    public function testTrashedNotVotable(): void
    {
        $poll = $this->makePoll();
        $poll->open();
        $poll->softDelete();

        self::assertTrue($poll->isTrashed());
        self::assertFalse($poll->isVotable());

        $poll->restore();
        self::assertFalse($poll->isTrashed());
        self::assertTrue($poll->isVotable());
    }

    // ─── ویرایش ──────────────────────────────────────────────────────────

    public function testReviseAndOptionsLockPrecondition(): void
    {
        $poll = $this->makePoll();
        $poll->revise(
            question: 'پرسش تازه؟',
            options: ['الف', 'ب'],
            multiple: true,
        );

        self::assertSame('پرسش تازه؟', $poll->question());
        self::assertSame(['الف', 'ب'], $poll->options());
        self::assertTrue($poll->multiple());
        self::assertNotNull($poll->updatedAt());
    }

    public function testReviseKeepsOptionsWhenNull(): void
    {
        $poll = $this->makePoll();
        $poll->revise(question: 'فقط پرسش عوض شد');

        self::assertSame(3, $poll->optionCount());
    }

    public function testReviseClearWindow(): void
    {
        $poll = $this->makePoll(endsAt: new \DateTimeImmutable('2026-12-01 00:00:00', new \DateTimeZone('UTC')));
        self::assertNotNull($poll->endsAt());

        $poll->revise(question: $poll->question(), clearWindow: true);
        self::assertNull($poll->endsAt());
    }

    public function testHasOptionBounds(): void
    {
        $poll = $this->makePoll();

        self::assertTrue($poll->hasOption(0));
        self::assertTrue($poll->hasOption(2));
        self::assertFalse($poll->hasOption(3));
        self::assertFalse($poll->hasOption(-1));
    }

    public function testChangeSlug(): void
    {
        $poll = $this->makePoll();
        $poll->changeSlug('new-slug');

        self::assertSame('new-slug', $poll->slug());
    }

    // ─── نگاشت ردیف ───────────────────────────────────────────────────────

    public function testToRowFromRowRoundtrip(): void
    {
        $starts = new \DateTimeImmutable('2026-10-01 08:30:00.500', new \DateTimeZone('UTC'));
        $ends = new \DateTimeImmutable('2026-11-01 20:45:00.250', new \DateTimeZone('UTC'));
        $poll = $this->makePoll(multiple: true, startsAt: $starts, endsAt: $ends);
        $poll->open();

        $copy = Poll::fromRow($poll->toRow());

        self::assertSame($poll->id(), $copy->id());
        self::assertSame($poll->slug(), $copy->slug());
        self::assertSame($poll->question(), $copy->question());
        self::assertSame($poll->options(), $copy->options());
        self::assertTrue($copy->multiple());
        self::assertSame(PollStatus::Open, $copy->status());
        self::assertSame(3, $copy->authorId());
        self::assertSame('2026-10-01 08:30:00.500', $copy->startsAt()?->format('Y-m-d H:i:s.v'));
        self::assertSame('2026-11-01 20:45:00.250', $copy->endsAt()?->format('Y-m-d H:i:s.v'));
        self::assertFalse($copy->isTrashed());
    }

    public function testFromRowToleratesBrokenOptionsJson(): void
    {
        $poll = Poll::fromRow([
            'id' => 'X2',
            'slug' => 'broken',
            'question' => 'گزینه‌های خراب',
            'options' => '{not-json',
        ]);

        self::assertSame([], $poll->options());
        self::assertSame(PollStatus::Draft, $poll->status());
    }

    // ─── VoteTally (دامنهٔ خالص) ─────────────────────────────────────────

    public function testTallyCountsAndPercentages(): void
    {
        $tally = new VoteTally();
        $rows = $tally->tally(['الف', 'ب', 'پ'], [0 => 3, 2 => 1]);

        self::assertCount(3, $rows);
        self::assertSame(3, $rows[0]['count']);
        self::assertSame(75.0, $rows[0]['percent']);
        self::assertSame(0, $rows[1]['count']);
        self::assertSame(0.0, $rows[1]['percent']);
        self::assertSame(25.0, $rows[2]['percent']);
        self::assertSame(4, $tally->total([0 => 3, 2 => 1]));
    }

    public function testTallyEmptyPoll(): void
    {
        $tally = new VoteTally();
        $rows = $tally->tally(['الف', 'ب'], []);

        self::assertSame(0.0, $rows[0]['percent']);
        self::assertSame(0, $tally->total([]));
        self::assertNull($tally->leader($rows));
    }

    public function testTallyLeaderAndTie(): void
    {
        $tally = new VoteTally();

        $rows = $tally->tally(['الف', 'ب'], [0 => 2, 1 => 9]);
        self::assertSame(1, $tally->leader($rows));

        // تساوی → نخستین اندیس
        $tie = $tally->tally(['الف', 'ب'], [0 => 5, 1 => 5]);
        self::assertSame(0, $tally->leader($tie));
    }

    public function testTallyIgnoresUnknownIndexesAndNegatives(): void
    {
        $tally = new VoteTally();
        $rows = $tally->tally(['الف', 'ب'], [0 => 1, 5 => 99, 1 => -3]);

        // اندیس ناشناخته (5) در ردیف‌ها نمی‌آید و درصد را بالا نمی‌برد
        self::assertSame(1, $rows[0]['count']);
        self::assertSame(100.0, $rows[0]['percent']);
        self::assertSame(0, $rows[1]['count']);   // منفی → صفر
        self::assertSame(0.0, $rows[1]['percent']);

        // total() جمع نقشهٔ ورودی است (مقادیر منفی صفر می‌شوند)
        self::assertSame(1, $tally->total([0 => 1, 1 => -3]));
    }
}

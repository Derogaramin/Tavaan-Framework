<?php

declare(strict_types=1);

namespace Modules\Poll\Tests\Unit;

use Modules\Poll\Application\UseCases\VoteService;
use Modules\Poll\Domain\Entities\Poll;
use Modules\Poll\Domain\Repositories\PollRepositoryInterface;
use Modules\Poll\Domain\Repositories\VoteRepositoryInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\TestCase;

/**
 * تست VoteService — یکتایی رأی (user/ip_hash)، اعتبار گزینه‌ها،
 * تک/چندانتخابی، پنجرهٔ زمانی، حریم خصوصی (هش IP) و قوانین مهمان.
 */
final class VoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private \Tavaan\Core\Application\Application $app;

    private VoteService $service;

    private PollRepositoryInterface $polls;

    private VoteRepositoryInterface $votes;

    private int $slugSeq = 0;

    protected function setUp(): void
    {
        $this->app = \tavaan_test_app();
        $this->setUpDatabase();

        $config = $this->app->make('config');
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }
        $config->set('cache.default', 'array');

        $this->polls = $this->app->make(PollRepositoryInterface::class);
        $this->votes = $this->app->make(VoteRepositoryInterface::class);
        $this->service = new VoteService($this->votes, [
            'voting' => ['guest_allowed' => true, 'unique_by' => ['user', 'ip']],
            'results' => 'after_vote',
        ]);
    }

    /** نظرسنجی بازِ آمادهٔ رأی‌گیری (بدون پنجرهٔ زمانی). */
    private function makeOpenPoll(bool $multiple = false, int $optionCount = 3): Poll
    {
        $options = [];
        for ($i = 1; $i <= $optionCount; $i++) {
            $options[] = 'گزینهٔ ' . $i;
        }

        $poll = Poll::draft(
            slug: 'vote-poll-' . (++$this->slugSeq) . '-' . \bin2hex(\random_bytes(3)),
            question: 'پرسش رأی‌گیری؟',
            options: $options,
            authorId: 1,
            multiple: $multiple,
        );
        $poll->open();
        $poll->pullEvents();
        $this->polls->save($poll);

        return $poll;
    }

    // ─── ثبت رأی ─────────────────────────────────────────────────────────

    public function testUserVoteStored(): void
    {
        $poll = $this->makeOpenPoll();

        $created = $this->service->cast($poll, [1], userId: 42, ip: '10.0.0.1');

        self::assertCount(1, $created);
        self::assertSame(1, $created[0]->optionIndex);
        self::assertSame(42, $created[0]->userId);
        self::assertNull($created[0]->ipHash);          // کاربر واردشده → بدون هش IP
        self::assertSame(1, $this->votes->countByPoll($poll->id()));
    }

    public function testGuestVoteStoresHashedIpOnly(): void
    {
        $poll = $this->makeOpenPoll();

        $created = $this->service->cast($poll, [0], userId: null, ip: '10.0.0.7');

        self::assertCount(1, $created);
        self::assertNull($created[0]->userId);
        self::assertNotNull($created[0]->ipHash);
        self::assertSame(64, \strlen((string) $created[0]->ipHash));
        self::assertStringNotContainsString('10.0.0.7', (string) $created[0]->ipHash);
    }

    public function testMultipleChoiceStoresOneRowPerOption(): void
    {
        $poll = $this->makeOpenPoll(multiple: true);

        $created = $this->service->cast($poll, ['2', 0], userId: 7, ip: null);

        self::assertCount(2, $created);
        self::assertSame(2, $this->votes->countByPoll($poll->id()));
        $counts = $this->votes->countsByPoll($poll->id());
        self::assertSame(1, $counts[0]);
        self::assertSame(1, $counts[2]);
    }

    // ─── اعتبار گزینه‌ها ─────────────────────────────────────────────────

    public function testInvalidOptionIndexesRejected(): void
    {
        $poll = $this->makeOpenPoll();

        try {
            $this->service->cast($poll, [9, 'abc', -1], userId: 1, ip: null);
            self::fail('گزینهٔ نامعتبر باید رد می‌شد');
        } catch (ValidationException $e) {
            self::assertSame('poll::messages.option_required', $e->messageId);
        }
        self::assertSame(0, $this->votes->countByPoll($poll->id()));
    }

    public function testSingleChoiceRejectsMultipleIndexes(): void
    {
        $poll = $this->makeOpenPoll(multiple: false);

        try {
            $this->service->cast($poll, [0, 1], userId: 2, ip: null);
            self::fail('نظرسنجی تک‌انتخابی باید چند گزینه را رد کند');
        } catch (ValidationException $e) {
            self::assertSame('poll::messages.single_choice_only', $e->messageId);
        }
    }

    public function testDuplicateIndexesDeduplicated(): void
    {
        $poll = $this->makeOpenPoll(multiple: true);

        $created = $this->service->cast($poll, [1, 1, 1], userId: 3, ip: null);

        self::assertCount(1, $created);
    }

    // ─── یکتایی رأی ─────────────────────────────────────────────────────

    public function testUserCannotVoteTwice(): void
    {
        $poll = $this->makeOpenPoll();
        $this->service->cast($poll, [0], userId: 42, ip: '10.0.0.1');

        try {
            $this->service->cast($poll, [1], userId: 42, ip: '10.9.9.9');   // IP متفاوت هم کمکی نمی‌کند
            self::fail('رأی دوم کاربر باید رد می‌شد');
        } catch (ValidationException $e) {
            self::assertSame('poll::messages.already_voted', $e->messageId);
        }
        self::assertSame(1, $this->votes->countByPoll($poll->id()));
    }

    public function testGuestCannotVoteTwiceFromSameIp(): void
    {
        $poll = $this->makeOpenPoll();
        $this->service->cast($poll, [0], userId: null, ip: '10.0.0.5');

        $this->expectException(ValidationException::class);
        $this->service->cast($poll, [1], userId: null, ip: '10.0.0.5');
    }

    public function testDifferentGuestsCanVote(): void
    {
        $poll = $this->makeOpenPoll();
        $this->service->cast($poll, [0], userId: null, ip: '10.0.0.5');
        $this->service->cast($poll, [1], userId: null, ip: '10.0.0.6');

        self::assertSame(2, $this->votes->countByPoll($poll->id()));
    }

    public function testHasVotedReflectsVoter(): void
    {
        $poll = $this->makeOpenPoll();
        $this->service->cast($poll, [0], userId: 42, ip: '10.0.0.1');

        self::assertTrue($this->service->hasVoted($poll, 42, '10.0.0.1'));
        self::assertFalse($this->service->hasVoted($poll, 43, '10.0.0.1'));

        $this->service->cast($poll, [1], userId: null, ip: '10.0.0.9');
        self::assertTrue($this->service->hasVoted($poll, null, '10.0.0.9'));
        self::assertFalse($this->service->hasVoted($poll, null, '10.0.0.10'));
    }

    // ─── قوانین رأی‌پذیری ────────────────────────────────────────────────

    public function testDraftPollNotVotable(): void
    {
        $poll = Poll::draft(slug: 'draft-poll-x', question: 'پیش‌نویس؟', options: ['الف', 'ب'], authorId: 1);
        $this->polls->save($poll);

        try {
            $this->service->cast($poll, [0], userId: 1, ip: null);
            self::fail('نظرسنجی پیش‌نویس باید رد می‌شد');
        } catch (ValidationException $e) {
            self::assertSame('poll::messages.not_votable', $e->messageId);
        }
    }

    public function testClosedPollNotVotable(): void
    {
        $poll = $this->makeOpenPoll();
        $poll->close();
        $this->polls->save($poll);

        $this->expectException(ValidationException::class);
        $this->service->cast($poll, [0], userId: 1, ip: null);
    }

    public function testExpiredWindowNotVotable(): void
    {
        $poll = Poll::draft(
            slug: 'expired-window',
            question: 'منقضی؟',
            options: ['الف', 'ب'],
            authorId: 1,
            endsAt: new \DateTimeImmutable('2020-01-01 00:00:00', new \DateTimeZone('UTC')),
        );
        $poll->open();
        $this->polls->save($poll);

        try {
            $this->service->cast($poll, [0], userId: 1, ip: null);
            self::fail('پنجرهٔ منقضی باید رد می‌شد');
        } catch (ValidationException $e) {
            self::assertSame('poll::messages.not_votable', $e->messageId);
        }
    }

    public function testGuestBlockedWhenNotAllowed(): void
    {
        $strict = new VoteService($this->votes, [
            'voting' => ['guest_allowed' => false, 'unique_by' => ['user']],
        ]);

        $poll = $this->makeOpenPoll();

        try {
            $strict->cast($poll, [0], userId: null, ip: '10.0.0.1');
            self::fail('مهمان باید رد می‌شد');
        } catch (ValidationException $e) {
            self::assertSame('poll::messages.login_required', $e->messageId);
        }

        // کاربر واردشده مجاز است
        $created = $strict->cast($poll, [0], userId: 11, ip: '10.0.0.1');
        self::assertCount(1, $created);
    }
}

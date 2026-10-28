<?php

declare(strict_types=1);

namespace Modules\Poll\Tests\Unit;

use Modules\Poll\Application\UseCases\PollWriteService;
use Modules\Poll\Domain\Entities\Poll;
use Modules\Poll\Domain\Entities\Vote;
use Modules\Poll\Domain\Enums\PollStatus;
use Modules\Poll\Domain\Repositories\PollRepositoryInterface;
use Modules\Poll\Domain\Repositories\VoteRepositoryInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\TestCase;

/**
 * تست PollWriteService — اعتبارسنجی گزینه‌ها، slug خودکار یونیک،
 * قفل گزینه‌ها پس از نخستین رأی، پنجرهٔ زمانی و چرخهٔ open/close/trash.
 */
final class PollWriteServiceTest extends TestCase
{
    use RefreshDatabase;

    private \Tavaan\Core\Application\Application $app;

    private PollWriteService $service;

    private PollRepositoryInterface $polls;

    private VoteRepositoryInterface $votes;

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
        $this->service = $this->app->make(PollWriteService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function input(mixed ...$overrides): array
    {
        $merged = [];
        foreach ($overrides as $key => $value) {
            if (\is_int($key) && \is_array($value)) {
                $merged = [...$merged, ...$value];
            } else {
                $merged[$key] = $value;
            }
        }

        return [
            'question' => 'پرسش پیش‌فرض آزمایشی؟',
            'options' => ['گزینهٔ یک', 'گزینهٔ دو'],
            ...$merged,
        ];
    }

    // ─── ساخت ────────────────────────────────────────────────────────────

    public function testCreateStoresDraftWithGeneratedSlug(): void
    {
        $poll = $this->service->create($this->input(), authorId: 5);

        self::assertSame(PollStatus::Draft, $poll->status());
        self::assertNotSame('', $poll->slug());
        self::assertSame(5, $poll->authorId());
        self::assertSame(['گزینهٔ یک', 'گزینهٔ دو'], $poll->options());

        $stored = $this->polls->findBySlug($poll->slug());
        self::assertNotNull($stored);
        self::assertSame($poll->id(), $stored->id());
    }

    public function testCreateWithStatusOpenOpensImmediately(): void
    {
        $poll = $this->service->create($this->input(status: 'open'), authorId: 1);

        self::assertSame(PollStatus::Open, $poll->status());
        self::assertTrue($poll->isVotable());
    }

    public function testCreateRequiresQuestion(): void
    {
        try {
            $this->service->create($this->input(question: ''), authorId: 1);
            self::fail('باید ValidationException می‌انداخت');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('question', $e->errors);
        }
    }

    public function testOptionsAreTrimmedAndEmptiesDropped(): void
    {
        $poll = $this->service->create($this->input(
            options: ['  الف  ', '', '   ', 'ب'],
        ), authorId: 1);

        self::assertSame(['الف', 'ب'], $poll->options());
    }

    public function testTooFewOptionsRejected(): void
    {
        try {
            $this->service->create($this->input(options: ['فقط یکی']), authorId: 1);
            self::fail('باید ValidationException می‌انداخت');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('options', $e->errors);
            self::assertSame('poll::messages.options_count', $e->messageId);
        }
    }

    public function testTooManyOptionsRejected(): void
    {
        $options = [];
        for ($i = 1; $i <= 11; $i++) {
            $options[] = 'گزینهٔ ' . $i;
        }

        $this->expectException(ValidationException::class);
        $this->service->create($this->input(options: $options), authorId: 1);
    }

    public function testDuplicateQuestionGetsUniqueSlug(): void
    {
        $first = $this->service->create($this->input(), authorId: 1);
        $second = $this->service->create($this->input(), authorId: 1);

        self::assertNotSame($first->slug(), $second->slug());
    }

    public function testInvalidWindowRejected(): void
    {
        try {
            $this->service->create($this->input(
                starts_at: '2026-11-01T00:00',
                ends_at: '2026-10-01T00:00',
            ), authorId: 1);
            self::fail('باید ValidationException می‌انداخت');
        } catch (ValidationException $e) {
            self::assertSame('poll::messages.window_invalid', $e->messageId);
        }
    }

    public function testWindowPersisted(): void
    {
        $poll = $this->service->create($this->input(
            starts_at: '2026-10-01T08:00',
            ends_at: '2026-12-01T20:30',
        ), authorId: 1);

        self::assertNotNull($poll->startsAt());
        self::assertNotNull($poll->endsAt());
        self::assertSame('2026-12-01 20:30', $poll->endsAt()?->format('Y-m-d H:i'));
    }

    // ─── ویرایش و قفل گزینه‌ها ────────────────────────────────────────────

    public function testUpdateChangesQuestionAndOptionsBeforeVotes(): void
    {
        $poll = $this->service->create($this->input(), authorId: 9);

        $updated = $this->service->update($poll, $this->input(
            question: 'پرسش ویرایش‌شده؟',
            options: ['یک', 'دو', 'سه'],
        ));

        self::assertSame('پرسش ویرایش‌شده؟', $updated->question());
        self::assertSame(['یک', 'دو', 'سه'], $updated->options());
        self::assertSame(9, $updated->authorId());

        $stored = $this->polls->findById($poll->id());
        self::assertNotNull($stored);
        self::assertSame('پرسش ویرایش‌شده؟', $stored->question());
    }

    public function testOptionsLockedAfterFirstVote(): void
    {
        $poll = $this->service->create($this->input(status: 'open'), authorId: 1);
        $this->votes->add(Vote::cast($poll->id(), 0, 42, null));

        try {
            $this->service->update($poll, $this->input(options: ['الف', 'ب', 'پ']));
            self::fail('تغییر گزینه‌ها پس از رأی باید رد می‌شد');
        } catch (ValidationException $e) {
            self::assertSame('poll::messages.options_locked', $e->messageId);
        }

        // اما ویرایش پرسش بدون تغییر گزینه‌ها مجاز است
        $updated = $this->service->update($poll, $this->input(
            question: 'پرسش بی‌خطر؟',
            options: ['گزینهٔ یک', 'گزینهٔ دو'],
        ));
        self::assertSame('پرسش بی‌خطر؟', $updated->question());
    }

    // ─── چرخهٔ وضعیت و حذف ───────────────────────────────────────────────

    public function testOpenCloseLifecycle(): void
    {
        $poll = $this->service->create($this->input(), authorId: 1);

        $this->service->open($poll);
        self::assertSame('open', $this->polls->findById($poll->id())?->status()->value);

        $this->service->close($poll);
        self::assertSame('closed', $this->polls->findById($poll->id())?->status()->value);
    }

    public function testOpenFromOpenThrows(): void
    {
        $poll = $this->service->create($this->input(status: 'open'), authorId: 1);

        $this->expectException(\DomainException::class);
        $this->service->open($poll);
    }

    public function testTrashSoftDeletesAndForceDeleteCascadesVotes(): void
    {
        $poll = $this->service->create($this->input(status: 'open'), authorId: 1);
        $this->votes->add(Vote::cast($poll->id(), 1, null, \hash('sha256', 'ip-test')));
        self::assertSame(1, $this->votes->countByPoll($poll->id()));

        $this->service->trash($poll);
        $trashed = $this->polls->findById($poll->id());
        self::assertNotNull($trashed);
        self::assertTrue($trashed->isTrashed());
        self::assertNull($this->polls->findBySlug($poll->slug()));
        self::assertSame(0, $this->polls->countOpen());

        $this->service->forceDelete($trashed);
        self::assertNull($this->polls->findById($poll->id()));
        self::assertSame(0, $this->votes->countByPoll($poll->id()));
    }

    public function testForceDeleteOnLivePollThrows(): void
    {
        $poll = $this->service->create($this->input(), authorId: 1);

        $this->expectException(\DomainException::class);
        $this->service->forceDelete($poll);
    }

    // ─── فهرست‌گیری مخزن ─────────────────────────────────────────────────

    public function testListOpenOnlyReturnsVotablePolls(): void
    {
        $open = $this->service->create($this->input(question: 'بازِ فعال؟'), authorId: 1);
        $this->service->open($open);

        $draft = $this->service->create($this->input(question: 'پیش‌نویس پنهان؟'), authorId: 1);

        $expired = $this->service->create($this->input(
            question: 'منقضی شده؟',
            starts_at: '2026-01-01T00:00',
            ends_at: '2026-02-01T00:00',
        ), authorId: 1);
        $this->service->open($expired);

        $rows = $this->polls->listOpen(10);
        $ids = \array_map(static fn (Poll $p): string => $p->id(), $rows);

        self::assertContains($open->id(), $ids);
        self::assertFalse(\in_array($draft->id(), $ids, true));
        self::assertFalse(\in_array($expired->id(), $ids, true));
    }
}

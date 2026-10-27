<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * PollWriteService — مسیر نوشتن نظرسنجی (create/update/open/close/trash).
 */

namespace Modules\Poll\Application\UseCases;

use Modules\Content\Domain\ValueObjects\Slug;
use Modules\Poll\Domain\Entities\Poll;
use Modules\Poll\Domain\Repositories\PollRepositoryInterface;
use Modules\Poll\Domain\Repositories\VoteRepositoryInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * PollWriteService — UseCase نوشتن نظرسنجی‌ها.
 *
 * 🔒 گزینه‌ها پس از نخستین رأی قفل می‌شوند (صحت جمع‌بندی نتایج).
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
final class PollWriteService
{
    /** @param array<string, mixed> $config کلید config('poll') */
    public function __construct(
        private readonly PollRepositoryInterface $polls,
        private readonly VoteRepositoryInterface $votes,
        private readonly array $config = [],
    ) {
    }

    /**
     * ساخت نظرسنجی پیش‌نویس (یا باز با status=open).
     *
     * @param array<string, mixed> $input question, slug?, options: list<string>, multiple?, starts_at?, ends_at?, status?
     *
     * @throws ValidationException
     */
    public function create(array $input, int $authorId): Poll
    {
        $validated = $this->validate($input);
        $options = $this->normalizeOptions($input['options'] ?? []);

        $slug = $this->resolveSlug((string) ($validated['slug'] ?? ''), (string) $validated['question']);
        [$startsAt, $endsAt] = $this->parseWindow(
            (string) ($validated['starts_at'] ?? ''),
            (string) ($validated['ends_at'] ?? ''),
        );

        $poll = Poll::draft(
            slug: $slug,
            question: (string) $validated['question'],
            options: $options,
            authorId: $authorId,
            multiple: (bool) ($input['multiple'] ?? false),
            startsAt: $startsAt,
            endsAt: $endsAt,
        );

        $this->polls->save($poll);

        if (($input['status'] ?? 'draft') === 'open') {
            $this->open($poll);
        }

        return $poll;
    }

    /**
     * ویرایش نظرسنجی (بدون تغییر نویسنده/وضعیت).
     *
     * @param array<string, mixed> $input
     *
     * @throws ValidationException
     */
    public function update(Poll $poll, array $input): Poll
    {
        $validated = $this->validate($input);

        $slug = Slug::slugify((string) ($validated['slug'] ?? $poll->slug()));
        if ($slug !== '' && $slug !== $poll->slug()) {
            if ($this->polls->slugExists($slug, exceptId: $poll->id())) {
                $slug = Slug::unique($slug, fn (string $c): bool => $this->polls->slugExists($c, exceptId: $poll->id()))->toString();
            }
            $poll->changeSlug($slug);
        }

        $options = null;
        if (\array_key_exists('options', $input)) {
            $candidates = $this->normalizeOptions($input['options']);
            if ($candidates !== $poll->options()) {
                if ($this->votes->countByPoll($poll->id()) > 0) {
                    throw new ValidationException(
                        errors: ['options' => ['گزینه‌ها پس از نخستین رأی قابل تغییر نیستند.']],
                        messageId: 'poll::messages.options_locked',
                    );
                }
                $options = $candidates;
            }
        }

        [$startsAt, $endsAt] = $this->parseWindow(
            (string) ($validated['starts_at'] ?? ''),
            (string) ($validated['ends_at'] ?? ''),
        );

        $poll->revise(
            question: (string) $validated['question'],
            options: $options,
            multiple: \array_key_exists('multiple', $input) ? (bool) $input['multiple'] : null,
            clearWindow: true,
            startsAt: $startsAt,
            endsAt: $endsAt,
        );

        $this->polls->save($poll);

        return $poll;
    }

    /** باز کردن رأی‌گیری — رویداد poll.opened. */
    public function open(Poll $poll): Poll
    {
        $poll->open();
        $this->polls->save($poll);
        $this->flushEvents($poll);

        return $poll;
    }

    /** بستن رأی‌گیری — رویداد poll.closed. */
    public function close(Poll $poll): Poll
    {
        $poll->close($this->votes->countByPoll($poll->id()));
        $this->polls->save($poll);
        $this->flushEvents($poll);

        return $poll;
    }

    /** حذف نرم. */
    public function trash(Poll $poll): Poll
    {
        $poll->softDelete();
        $this->polls->save($poll);
        Event::dispatch('poll.deleted', ['poll_id' => $poll->id(), 'slug' => $poll->slug()]);

        return $poll;
    }

    /** حذف سخت (فقط trash‌شده) — رأی‌ها هم پاک می‌شوند. */
    public function forceDelete(Poll $poll): void
    {
        if (!$poll->isTrashed()) {
            throw new \DomainException('فقط نظرسنجی حذف‌شده (trash) به‌صورت سخت پاک می‌شود.');
        }

        $this->votes->deleteByPoll($poll->id());
        $this->polls->delete($poll->id());
        Event::dispatch('poll.deleted', ['poll_id' => $poll->id(), 'slug' => $poll->slug(), 'force' => true]);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validate(array $input): array
    {
        return Validator::validate($input, [
            'question' => ['required', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:200'],
            'starts_at' => ['nullable', 'string', 'max:40'],
            'ends_at' => ['nullable', 'string', 'max:40'],
        ], attributes: [
            'question' => 'پرسش',
        ]);
    }

    /**
     * نرمال‌سازی گزینه‌ها: trim، حذف خالی‌ها، بررسی سقف/کف.
     *
     * @return list<string>
     *
     * @throws ValidationException
     */
    private function normalizeOptions(mixed $raw): array
    {
        if (!\is_array($raw)) {
            throw new ValidationException(
                errors: ['options' => ['دست‌کم دو گزینه وارد کنید.']],
                messageId: 'poll::messages.options_required',
            );
        }

        $options = [];
        foreach ($raw as $option) {
            if (\is_array($option) || \is_object($option)) {
                continue;
            }
            $label = \trim((string) $option);
            if ($label === '') {
                continue;
            }
            if (\mb_strlen($label) > 255) {
                $label = \mb_substr($label, 0, 255);
            }
            $options[] = $label;
        }

        $min = (int) ($this->config['min_options'] ?? 2);
        $max = (int) ($this->config['max_options'] ?? 10);

        if (\count($options) < $min || \count($options) > $max) {
            throw new ValidationException(
                errors: ['options' => [\sprintf('تعداد گزینه‌ها باید بین %d و %d باشد.', $min, $max)]],
                messageId: 'poll::messages.options_count',
            );
        }

        return $options;
    }

    private function resolveSlug(string $explicit, string $question): string
    {
        $base = $explicit !== '' ? Slug::slugify($explicit) : Slug::slugify($question);
        if ($base === '') {
            $base = 'poll';
        }

        return Slug::unique($base, fn (string $candidate): bool => $this->polls->slugExists($candidate))->toString();
    }

    /**
     * تجزیهٔ پنجرهٔ زمانی (datetime-local یا هر قالب قابل تجزیه).
     *
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     *
     * @throws ValidationException
     */
    private function parseWindow(string $startsRaw, string $endsRaw): array
    {
        $startsAt = $this->parseDate($startsRaw, 'starts_at');
        $endsAt = $this->parseDate($endsRaw, 'ends_at');

        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw new ValidationException(
                errors: ['ends_at' => ['پایان رأی‌گیری باید پس از شروع آن باشد.']],
                messageId: 'poll::messages.window_invalid',
            );
        }

        return [$startsAt, $endsAt];
    }

    private function parseDate(string $raw, string $field): ?\DateTimeImmutable
    {
        $raw = \trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            throw new ValidationException(
                errors: [$field => ['قالب تاریخ نامعتبر است.']],
                messageId: 'poll::messages.date_invalid',
            );
        }
    }

    /** انتشار رویدادهای دامنهٔ ثبت‌شده روی entity. */
    private function flushEvents(Poll $poll): void
    {
        foreach ($poll->pullEvents() as $event) {
            $name = \method_exists($event, 'eventName') ? (string) $event->eventName() : '';
            if ($name === '') {
                continue;
            }

            Event::dispatch($name, [
                'poll_id' => $poll->id(),
                'slug' => $poll->slug(),
                'question' => $poll->question(),
            ]);
        }
    }
}

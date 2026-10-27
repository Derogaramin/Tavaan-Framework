<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * Poll — ریشهٔ Aggregate نظرسنجی.
 */

namespace Modules\Poll\Domain\Entities;

use Modules\Poll\Domain\Enums\PollStatus;

/**
 * Poll — یک نظرسنجی (پرسش + گزینه‌ها + پنجرهٔ زمانی رأی‌گیری).
 *
 * invariantها:
 * - حداقل دو گزینهٔ غیرخالی (در ساخت/ویرایش توسط UseCase بازبینی می‌شود).
 * - انتقال وضعیت فقط طبق ماتریس PollStatus::canTransitionTo.
 * - `isVotable()` زمان‌محور است (starts_at/ends_at) → بدون نیاز به cron.
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
final class Poll
{
    /** @var list<object> رویدادهای دامنهٔ ثبت‌شده (pull توسط UseCase) */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private string $slug,
        private string $question,
        private array $options,
        private bool $multiple,
        private PollStatus $status,
        private ?\DateTimeImmutable $startsAt,
        private ?\DateTimeImmutable $endsAt,
        private readonly int $authorId,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $deletedAt,
    ) {
    }

    /**
     * ساخت نظرسنجی پیش‌نویس (شناسهٔ ULID).
     *
     * @param list<string> $options
     */
    public static function draft(
        string $slug,
        string $question,
        array $options,
        int $authorId,
        bool $multiple = false,
        ?\DateTimeImmutable $startsAt = null,
        ?\DateTimeImmutable $endsAt = null,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            slug: $slug,
            question: $question,
            options: \array_values(\array_map('strval', $options)),
            multiple: $multiple,
            status: PollStatus::Draft,
            startsAt: $startsAt,
            endsAt: $endsAt,
            authorId: $authorId,
            createdAt: $createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * بازسازی از ردیف دیتابیس (mapper مخزن).
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $options = [];
        $decoded = \json_decode((string) ($row['options'] ?? '[]'), true);
        if (\is_array($decoded)) {
            foreach ($decoded as $option) {
                if (\is_scalar($option)) {
                    $options[] = (string) $option;
                }
            }
        }

        return new self(
            id: (string) ($row['id'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
            question: (string) ($row['question'] ?? ''),
            options: $options,
            multiple: (bool) ($row['multiple'] ?? false),
            status: PollStatus::tryFrom((string) ($row['status'] ?? 'draft')) ?? PollStatus::Draft,
            startsAt: self::date($row['starts_at'] ?? null),
            endsAt: self::date($row['ends_at'] ?? null),
            authorId: (int) ($row['author_id'] ?? 0),
            createdAt: self::date($row['created_at'] ?? null),
            updatedAt: self::date($row['updated_at'] ?? null),
            deletedAt: self::date($row['deleted_at'] ?? null),
        );
    }

    // ─── رفتارسازی دامنه ──────────────────────────────────────────────────

    /**
     * ویرایش پرسش/گزینه‌ها/پنجرهٔ زمانی (بدون تغییر وضعیت).
     *
     * @param list<string>|null $options
     */
    public function revise(
        string $question,
        ?array $options = null,
        ?bool $multiple = null,
        ?string $slug = null,
        ?\DateTimeImmutable $startsAt = null,
        ?\DateTimeImmutable $endsAt = null,
        bool $clearWindow = false,
    ): void {
        $this->question = $question;
        if ($options !== null) {
            $this->options = \array_values(\array_map('strval', $options));
        }
        if ($multiple !== null) {
            $this->multiple = $multiple;
        }
        if ($slug !== null && $slug !== '') {
            $this->slug = $slug;
        }
        if ($clearWindow) {
            $this->startsAt = $startsAt;
            $this->endsAt = $endsAt;
        } else {
            $this->startsAt = $startsAt ?? $this->startsAt;
            $this->endsAt = $endsAt ?? $this->endsAt;
        }
        $this->touch();
    }

    /** باز کردن رأی‌گیری — رویداد poll.opened. */
    public function open(): void
    {
        $this->assertTransition(PollStatus::Open);
        $this->status = PollStatus::Open;
        $this->touch();
        $this->recordedEvents[] = new \Modules\Poll\Domain\Events\PollWasOpened($this->id, $this->slug, $this->question);
    }

    /** بستن رأی‌گیری — رویداد poll.closed. */
    public function close(int $totalVotes = 0): void
    {
        $this->assertTransition(PollStatus::Closed);
        $this->status = PollStatus::Closed;
        $this->touch();
        $this->recordedEvents[] = new \Modules\Poll\Domain\Events\PollWasClosed($this->id, $this->slug, $this->question, $totalVotes);
    }

    public function changeSlug(string $slug): void
    {
        $this->slug = $slug;
        $this->touch();
    }

    public function softDelete(?\DateTimeImmutable $at = null): void
    {
        $this->deletedAt = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function restore(): void
    {
        $this->deletedAt = null;
        $this->touch();
    }

    // ─── پرسش‌های دامنه ───────────────────────────────────────────────────

    /**
     * آیا همین لحظه رأی‌گیری فعال است؟ (وضعیت + پنجرهٔ زمانی + trash)
     */
    public function isVotable(?\DateTimeImmutable $now = null): bool
    {
        if ($this->status !== PollStatus::Open || $this->deletedAt !== null) {
            return false;
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($this->startsAt !== null && $now < $this->startsAt) {
            return false;
        }

        return $this->endsAt === null || $now <= $this->endsAt;
    }

    /** آیا مهلت تمام شده (بدون توجه به وضعیت)؟ */
    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        if ($this->endsAt === null) {
            return false;
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $now > $this->endsAt;
    }

    public function hasOption(int $index): bool
    {
        return $index >= 0 && $index < \count($this->options);
    }

    // ─── Getterها ─────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function question(): string
    {
        return $this->question;
    }

    /** @return list<string> */
    public function options(): array
    {
        return $this->options;
    }

    public function optionCount(): int
    {
        return \count($this->options);
    }

    public function multiple(): bool
    {
        return $this->multiple;
    }

    public function status(): PollStatus
    {
        return $this->status;
    }

    public function startsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function authorId(): int
    {
        return $this->authorId;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isTrashed(): bool
    {
        return $this->deletedAt !== null;
    }

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'question' => $this->question,
            'options' => (string) \json_encode($this->options, \JSON_UNESCAPED_UNICODE),
            'multiple' => $this->multiple ? 1 : 0,
            'status' => $this->status->value,
            'starts_at' => $this->startsAt?->format('Y-m-d H:i:s.v'),
            'ends_at' => $this->endsAt?->format('Y-m-d H:i:s.v'),
            'author_id' => $this->authorId,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s.v'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s.v'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s.v'),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function assertTransition(PollStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(
                \sprintf('Invalid poll status transition: %s → %s', $this->status->value, $target->value),
            );
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}

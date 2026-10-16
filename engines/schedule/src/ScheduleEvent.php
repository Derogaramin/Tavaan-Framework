<?php

declare(strict_types=1);

namespace Tavaan\Engine\Schedule;

/**
 * تَوان | Tavaan — Schedule
 * ScheduleEvent — رویداد زمان‌بندی‌شده با API فلونت (DOC-062 §2).
 *
 * ```php
 * Schedule::command('orders:cleanup')->daily()->at('03:00');
 * Schedule::command('backup:run')->daily()->withoutOverlapping();
 * Schedule::command('x')->everyMinute()->onOneServer();
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/schedule.md
 */
final class ScheduleEvent
{
    private CronExpression $cron;

    private ?string $timezone = null;

    private string $description = '';

    /** نوع وظیفه: command|job|call|exec */
    private readonly string $type;

    /** @var mixed نام دستور / شیء job / closure / رشتهٔ shell */
    private readonly mixed $task;

    /** @var array<int|string, mixed> */
    private readonly array $arguments;

    /** @var list<callable> */
    private array $filters = [];

    /** @var array{0: string, 1: string}|null */
    private ?array $betweenRange = null;

    private bool $negateBetween = false;

    private bool $withoutOverlapping = false;

    private int $overlapExpiresAt = 1440;   // دقیقه

    private bool $onOneServer = false;

    private bool $runInBackground = false;

    private bool $evenInMaintenanceMode = false;

    /** @var list<callable> */
    private array $successCallbacks = [];

    /** @var list<callable> */
    private array $failureCallbacks = [];

    private ?string $outputPath = null;

    private ?string $queue = null;

    private ?string $connection = null;

    /**
     * @param mixed                 $task
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(string $type, mixed $task, array $arguments = [])
    {
        $this->type = $type;
        $this->task = $task;
        $this->arguments = $arguments;
        $this->cron = new CronExpression('* * * * *');   // پیش‌فرض: هر دقیقه
    }

    // ─── بسامد (Fluent) ───────────────────────────────────────────────────

    public function cron(string $expression): self
    {
        $this->cron = new CronExpression($expression);

        return $this;
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function everyTwoMinutes(): self
    {
        return $this->cron('*/2 * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): self
    {
        return $this->cron('0,30 * * * *');
    }

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int|string $minute): self
    {
        return $this->cron(((string) $minute) . ' * * * *');
    }

    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    /** @param string $time `HH:MM` */
    public function dailyAt(string $time): self
    {
        return $this->at($time);
    }

    /** @param string $time `HH:MM` — همان dailyAt (DOC-062 §2) */
    public function at(string $time): self
    {
        [$hour, $minute] = $this->parseTime($time);

        return $this->cron(\sprintf('%d %d * * *', $minute, $hour));
    }

    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    /** @param string $time `HH:MM` — weeklyOn(5, '17:00') = جمعهٔ… (روز هفتهٔ cron) */
    public function weeklyOn(int $dayOfWeek, string $time = '00:00'): self
    {
        [$hour, $minute] = $this->parseTime($time);

        return $this->cron(\sprintf('%d %d * * %d', $minute, $hour, $dayOfWeek));
    }

    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    /** @param string $time `HH:MM` */
    public function monthlyOn(int $dayOfMonth, string $time = '00:00'): self
    {
        [$hour, $minute] = $this->parseTime($time);

        return $this->cron(\sprintf('%d %d %d * *', $minute, $hour, $dayOfMonth));
    }

    public function quarterly(): self
    {
        return $this->cron('0 0 1 1,4,7,10 *');
    }

    public function yearly(): self
    {
        return $this->cron('0 0 1 1 *');
    }

    // ─── قیدها ────────────────────────────────────────────────────────────

    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /** فقط بین دو ساعت — مثل `between('09:00','17:00')` (محدودهٔ شبهانه پشتیبانی می‌شود). */
    public function between(string $start, string $end): self
    {
        $this->betweenRange = [$start, $end];
        $this->negateBetween = false;

        return $this;
    }

    public function unlessBetween(string $start, string $end): self
    {
        $this->betweenRange = [$start, $end];
        $this->negateBetween = true;

        return $this;
    }

    /** شرط اجرا — callback باید true برگرداند. */
    public function when(callable $callback): self
    {
        $this->filters[] = $callback;

        return $this;
    }

    /** معکوس when. */
    public function skip(callable $callback): self
    {
        $this->filters[] = static fn (): bool => !$callback();

        return $this;
    }

    /** 🔒 جلوگیری از اجرای همزمان (Mutex با add اتمیک — DOC-062 §4.1). */
    public function withoutOverlapping(int $expiresAtMinutes = 1440): self
    {
        $this->withoutOverlapping = true;
        $this->overlapExpiresAt = \max(1, $expiresAtMinutes);

        return $this;
    }

    /** 🔒 در چند سرور، فقط یک سرور اجرا کند (DOC-062 §4.2). */
    public function onOneServer(): self
    {
        $this->onOneServer = true;

        return $this;
    }

    public function runInBackground(): self
    {
        $this->runInBackground = true;

        return $this;
    }

    public function evenInMaintenanceMode(): self
    {
        $this->evenInMaintenanceMode = true;

        return $this;
    }

    public function onSuccess(callable $callback): self
    {
        $this->successCallbacks[] = $callback;

        return $this;
    }

    public function onFailure(callable $callback): self
    {
        $this->failureCallbacks[] = $callback;

        return $this;
    }

    /** خروجی اجرا در این فایل ذخیره شود (append). */
    public function output(string $path): self
    {
        $this->outputPath = $path;

        return $this;
    }

    public function onQueue(string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    public function onConnection(string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /** نام/توضیح کوتاه رویداد (برای نمایش در schedule:list). */
    public function name(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    // ─── ارزیابی ──────────────────────────────────────────────────────────

    /** آیا در این لحظه (با timezone رویداد) باید اجرا شود؟ */
    public function isDue(\DateTimeImmutable $now): bool
    {
        $local = $this->localTime($now);

        if (!$this->cron->isDue($local)) {
            return false;
        }

        return $this->betweenPasses($local);
    }

    /** فیلترهای when/skip. */
    public function filtersPass(): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter()) {
                return false;
            }
        }

        return true;
    }

    // ─── Getterها ─────────────────────────────────────────────────────────

    public function type(): string
    {
        return $this->type;
    }

    public function task(): mixed
    {
        return $this->task;
    }

    /** @return array<int|string, mixed> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function expression(): string
    {
        return $this->cron->expression();
    }

    public function cronExpression(): CronExpression
    {
        return $this->cron;
    }

    public function timezoneName(): ?string
    {
        return $this->timezone;
    }

    public function description(): string
    {
        return $this->description !== '' ? $this->description : $this->summary();
    }

    /** خلاصهٔ خوانا (command/className/closure). */
    public function summary(): string
    {
        return match ($this->type) {
            'command' => (string) $this->task . ($this->arguments !== [] ? ' ' . \implode(' ', \array_map(strval(...), $this->arguments)) : ''),
            'exec' => (string) $this->task,
            'job' => \is_object($this->task) ? $this->task::class : (string) $this->task,
            default => 'closure',
        };
    }

    public function wantsWithoutOverlapping(): bool
    {
        return $this->withoutOverlapping;
    }

    public function overlapTtlSeconds(): int
    {
        return $this->overlapExpiresAt * 60;
    }

    public function wantsOnOneServer(): bool
    {
        return $this->onOneServer;
    }

    public function wantsBackground(): bool
    {
        return $this->runInBackground;
    }

    public function runsInMaintenanceMode(): bool
    {
        return $this->evenInMaintenanceMode;
    }

    public function outputPath(): ?string
    {
        return $this->outputPath;
    }

    public function queueName(): ?string
    {
        return $this->queue;
    }

    public function connectionName(): ?string
    {
        return $this->connection;
    }

    /** @return list<callable> */
    public function successCallbacks(): array
    {
        return $this->successCallbacks;
    }

    /** @return list<callable> */
    public function failureCallbacks(): array
    {
        return $this->failureCallbacks;
    }

    /** کلید Mutex ( DOC-062 §4.1). */
    public function mutexName(): string
    {
        return 'schedule:mutex:' . \sha1($this->expression() . '|' . $this->type . '|' . $this->summary());
    }

    /** کلید قفل onOneServer برای دقیقهٔ جاری (DOC-062 §4.2). */
    public function serverLockName(\DateTimeImmutable $local): string
    {
        return 'schedule:lock:' . \sha1($this->summary()) . ':' . $local->format('YmdHi');
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function localTime(\DateTimeImmutable $now): \DateTimeImmutable
    {
        if ($this->timezone === null) {
            return $now;
        }

        return $now->setTimezone(new \DateTimeZone($this->timezone));
    }

    private function betweenPasses(\DateTimeImmutable $local): bool
    {
        if ($this->betweenRange === null) {
            return true;
        }

        [$start, $end] = $this->betweenRange;
        $current = (int) $local->format('Hi');
        $startInt = $this->timeToInt($start);
        $endInt = $this->timeToInt($end);

        $inside = $startInt <= $endInt
            ? ($current >= $startInt && $current <= $endInt)
            : ($current >= $startInt || $current <= $endInt);   // محدودهٔ شبهانه

        return $this->negateBetween ? !$inside : $inside;
    }

    /** @return array{0: int, 1: int} [hour, minute] */
    private function parseTime(string $time): array
    {
        if (\preg_match('/^(\d{1,2}):(\d{2})$/', \trim($time), $m) !== 1) {
            throw new \InvalidArgumentException(\sprintf('زمان نامعتبر [%s] — قالب HH:MM.', $time));
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];

        if ($hour > 23 || $minute > 59) {
            throw new \InvalidArgumentException(\sprintf('زمان نامعتبر [%s].', $time));
        }

        return [$hour, $minute];
    }

    private function timeToInt(string $time): int
    {
        [$hour, $minute] = $this->parseTime($time);

        return $hour * 100 + $minute;
    }
}

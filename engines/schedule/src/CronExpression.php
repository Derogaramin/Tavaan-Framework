<?php

declare(strict_types=1);

namespace Tavaan\Engine\Schedule;

use Tavaan\Engine\Schedule\Exceptions\InvalidCronException;

/**
 * تَوان | Tavaan — Schedule
 * CronExpression — پارسر خودنوشتهٔ عبارت Cron (پنج فیلد؛ DOC-062 §3).
 *
 * قالب: `minute hour dayOfMonth month dayOfWeek`
 * پشتیبانی: `*`، فهرست `1,15`، بازه `1-5`، گام `*\/5` و `1-30/5`،
 * و `7` به‌جای `0` برای یکشنبه.
 *
 * 🔒 مانند cron کلاسیک: وقتی **هم** dayOfMonth و **هم** dayOfWeek
 * محدود شده باشند (هیچ‌کدام `*` نباشد)، رابطهٔ آن‌ها OR است.
 *
 * @api 1.0
 * @see docs/03-engines/schedule.md
 */
final class CronExpression
{
    /**
     * کران‌های هر فیلد [min, max].
     *
     * @var array<int, array{0: int, 1: int}>
     */
    private const BOUNDS = [
        [0, 59],    // minute
        [0, 23],    // hour
        [1, 31],    // day of month
        [1, 12],    // month
        [0, 6],     // day of week (7 = یکشنبه → 0)
    ];

    /** @var list<string> */
    private readonly array $parts;

    public function __construct(private readonly string $expression)
    {
        $parts = \preg_split('/\s+/', \trim($expression)) ?: [];
        if (\count($parts) !== 5) {
            throw new InvalidCronException($expression, 'عبارت cron باید دقیقاً ۵ فیلد داشته باشد.');
        }

        foreach ($parts as $part) {
            if (!self::isValidField($part)) {
                throw new InvalidCronException($expression, \sprintf('فیلد نامعتبر: %s', $part));
            }
        }

        $this->parts = $parts;
    }

    /** آیا در این لحظه باید اجرا شود؟ */
    public function isDue(\DateTimeImmutable $time): bool
    {
        $minute = (int) $time->format('i');
        $hour = (int) $time->format('G');
        $dom = (int) $time->format('j');
        $month = (int) $time->format('n');
        $dow = (int) $time->format('w');

        if (!$this->matches($this->parts[0], $minute, 0)) {
            return false;
        }
        if (!$this->matches($this->parts[1], $hour, 1)) {
            return false;
        }
        if (!$this->matches($this->parts[3], $month, 3)) {
            return false;
        }

        // 🔒 cron کلاسیک: DOM/DOW هر دو محدود → OR
        $domRestricted = $this->parts[2] !== '*';
        $dowRestricted = $this->parts[4] !== '*';

        if ($domRestricted && $dowRestricted) {
            return $this->matches($this->parts[2], $dom, 2) || $this->matches($this->parts[4], $dow, 4);
        }

        return $this->matches($this->parts[2], $dom, 2) && $this->matches($this->parts[4], $dow, 4);
    }

    /**
     * زمان‌های بعدی اجرا (برای preview / schedule:list).
     *
     * @return list<\DateTimeImmutable>
     */
    public function nextRunDates(int $count, \DateTimeImmutable $from): array
    {
        $count = \max(1, $count);
        $runs = [];

        // دقیقهٔ جاری را رد می‌کنیم تا «بعدی» واقعاً بعدی باشد
        $cursor = $from->setTime((int) $from->format('G'), (int) $from->format('i'), 0)
            ->modify('+1 minute');

        // حداکثر ~۴ سال پیمایش (جلوگیری از حلقهٔ بی‌پایان)
        $maxIterations = 366 * 24 * 60 * 4;
        for ($i = 0; $i < $maxIterations && \count($runs) < $count; $i++) {
            if ($this->isDue($cursor)) {
                $runs[] = $cursor;
            }
            $cursor = $cursor->modify('+1 minute');
        }

        return $runs;
    }

    public function expression(): string
    {
        return $this->expression;
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /**
     * تطبیق یک فیلد با مقدار — `*`، فهرست، بازه و گام (DOC-062 §3).
     */
    private function matches(string $field, int $value, int $fieldIndex): bool
    {
        if ($field === '*') {
            return true;
        }

        foreach (\explode(',', $field) as $part) {
            $step = 1;
            $range = $part;

            if (\str_contains($part, '/')) {
                [$range, $stepStr] = \explode('/', $part, 2);
                $step = \max(1, (int) $stepStr);
            }

            if ($range === '*') {
                [$min, $max] = self::BOUNDS[$fieldIndex];
            } elseif (\str_contains($range, '-')) {
                [$minStr, $maxStr] = \explode('-', $range, 2);
                $min = (int) $minStr;
                $max = (int) $maxStr;
            } else {
                $min = (int) $range;
                // `N/step` → از N تا کران بالا؛ `N` تنها → فقط همان مقدار
                $max = $step > 1 ? self::BOUNDS[$fieldIndex][1] : $min;
            }

            // 🔒 یکشنبه با 7 هم مطابقت می‌کند
            $candidates = $fieldIndex === 4 && $value === 0 ? [0, 7] : [$value];

            foreach ($candidates as $candidate) {
                if ($candidate >= $min && $candidate <= $max && ($candidate - $min) % $step === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isValidField(string $field): bool
    {
        return \preg_match('/^(\*|\d+(-\d+)?)(\/\d+)?(,(\*|\d+(-\d+)?)(\/\d+)?)*$/', $field) === 1;
    }
}

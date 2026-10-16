<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

use Tavaan\Engine\Workflow\Exceptions\WorkflowException;

/**
 * تَوان | Tavaan — Workflow
 * Duration — پارس مدت‌زمان تایمر: انسانی (`3 days`) یا ISO-8601 (`P3D`).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class Duration
{
    private function __construct()
    {
    }

    /**
     * پارس رشتهٔ مدت به DateInterval.
     *
     * @throws WorkflowException رشتهٔ نامعتبر → `workflow.invalid_duration`
     */
    public static function parse(string $after): \DateInterval
    {
        $after = \trim($after);
        if ($after === '') {
            throw WorkflowException::invalidDuration($after);
        }

        // ISO-8601 (P3D / PT4H / P1W …)
        if ($after[0] === 'P' || $after[0] === 'p') {
            try {
                return new \DateInterval($after);
            } catch (\Exception) {
                throw WorkflowException::invalidDuration($after);
            }
        }

        // انسانی: «3 days» / «+2 hours» / «1 week»
        $probe = @\strtotime($after, 0);
        if ($probe === false || $probe === 0) {
            throw WorkflowException::invalidDuration($after);
        }

        $seconds = $probe;
        $interval = new \DateInterval('PT' . $seconds . 'S');

        return $interval;
    }

    /** افزودن مدت به یک لحظه (بدون جهش DST — مبتنی بر ثانیه/تقویم). */
    public static function add(\DateTimeImmutable $from, string $after): \DateTimeImmutable
    {
        return $from->add(self::parse($after));
    }
}

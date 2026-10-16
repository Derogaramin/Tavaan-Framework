<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Fixtures;

/** Fixture: callback پایان batch (invokable — قابل سریال‌سازی به‌شکل کلاس). */
final class BatchFinally
{
    /** @var list<string> */
    public static array $recorded = [];

    public function __invoke(\Tavaan\Engine\Queue\Batch\Batch $batch): void
    {
        self::$recorded[] = $batch->id() . ':' . $batch->name() . ':' . $batch->failedJobs();
    }

    public static function reset(): void
    {
        self::$recorded = [];
    }
}

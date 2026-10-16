<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Fixtures;

use Tavaan\Core\Contracts\Queue\ShouldQueue;
use Tavaan\Engine\Queue\Queueable;

/** Fixture: job شمارنده برای تست Batch. */
final class BatchCountJob implements ShouldQueue
{
    use Queueable;

    public static int $count = 0;

    public function __construct(public readonly string $tag = 'batch')
    {
    }

    public function handle(): void
    {
        self::$count++;
    }

    public static function reset(): void
    {
        self::$count = 0;
    }
}

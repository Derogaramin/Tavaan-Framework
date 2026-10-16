<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Fixtures;

use Tavaan\Core\Contracts\Queue\ShouldQueue;
use Tavaan\Engine\Queue\Queueable;

/** Fixture: job کند برای تست timeout (DOC-061 §4.2). */
final class TimeoutJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1;

    public static bool $completed = false;

    public function handle(): void
    {
        \usleep(1_400_000);   // بیش از timeout=1s
        self::$completed = true;
    }

    public static function reset(): void
    {
        self::$completed = false;
    }
}

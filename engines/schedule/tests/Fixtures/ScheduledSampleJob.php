<?php

declare(strict_types=1);

namespace Tavaan\Engine\Schedule\Tests\Fixtures;

use Tavaan\Core\Contracts\Queue\ShouldQueue;

/** Fixture: job صف‌روند برای Schedule::job. */
final class ScheduledSampleJob implements ShouldQueue
{
    /** @var list<string> */
    public static array $trace = [];

    public function __construct(public readonly string $tag = 'scheduled')
    {
    }

    public function handle(): void
    {
        self::$trace[] = $this->tag;
    }

    public static function reset(): void
    {
        self::$trace = [];
    }
}

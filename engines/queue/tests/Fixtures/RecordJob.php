<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Fixtures;

use Tavaan\Core\Contracts\Queue\ShouldQueue;
use Tavaan\Engine\Queue\Queueable;

/** Fixture: job ساده با ردیابی اجرا. */
final class RecordJob implements ShouldQueue
{
    use Queueable;

    /** @var list<string> */
    public static array $trace = [];

    public function __construct(public readonly string $tag = 'default')
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

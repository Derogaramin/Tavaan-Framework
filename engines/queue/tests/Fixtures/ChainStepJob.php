<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Fixtures;

use Tavaan\Core\Contracts\Queue\ShouldQueue;
use Tavaan\Engine\Queue\Queueable;

/** Fixture: عضو زنجیره — نام خود را در رد مشترک ثبت می‌کند. */
final class ChainStepJob implements ShouldQueue
{
    use Queueable;

    /** @var list<string> */
    public static array $trace = [];

    public function __construct(public readonly string $name = 'step')
    {
    }

    public function handle(): void
    {
        self::$trace[] = $this->name;
    }

    public static function reset(): void
    {
        self::$trace = [];
    }
}

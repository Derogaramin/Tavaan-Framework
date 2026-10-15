<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pipeline;

/** Fixture: ضبط ترتیب onion. */
final class PipelineTrace
{
    /** @var list<string> */
    public static array $log = [];

    public static function reset(): void
    {
        self::$log = [];
    }

    public static function record(string $entry): void
    {
        self::$log[] = $entry;
    }
}

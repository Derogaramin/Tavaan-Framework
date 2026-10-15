<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

/** Fixture: ضبط ترتیب اجرای Listenerها. */
final class ListenerRecorder
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

<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

/** Fixture: Listener با خطا. */
final class FailingListener
{
    public function handle(): void
    {
        ListenerRecorder::record('failing:before');
        throw new \RuntimeException('listener boom');
    }
}

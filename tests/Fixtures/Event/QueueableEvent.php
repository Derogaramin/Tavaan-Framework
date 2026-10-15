<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

use Tavaan\Core\Contracts\Events\ShouldQueue;

/** Fixture: رویداد صف‌رو. */
final class QueueableEvent implements ShouldQueue
{
    public function __construct(public readonly string $id = 'q1')
    {
    }
}

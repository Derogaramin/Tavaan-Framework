<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

use Tavaan\Core\Events\Event;

/** Fixture: رویداد Stoppable. */
final class StoppableSample extends Event
{
    public function __construct(public readonly string $value = 'x')
    {
    }
}

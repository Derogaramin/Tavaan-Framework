<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Fixtures;

use Tavaan\Core\Contracts\Events\ShouldQueue;

/** Fixture: رویداد صف‌روند — roundtrip با EventDispatcher (DOC-061/DOC-033). */
final class QueuedSampleEvent implements ShouldQueue
{
    public function __construct(public readonly string $info = '')
    {
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Events;

/**
 * Marker: این رویداد باید Broadcast شود (موتور realtime).
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
interface ShouldBroadcast
{
    /** @return list<string> */
    public function broadcastOn(): array;

    public function broadcastAs(): string;
}

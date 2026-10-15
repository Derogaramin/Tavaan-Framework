<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Events;

/**
 * رویدادی که Listenerها می‌توانند انتشار آن را متوقف کنند.
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
interface StoppableEventInterface
{
    public function stopPropagation(): void;

    public function isPropagationStopped(): bool;
}

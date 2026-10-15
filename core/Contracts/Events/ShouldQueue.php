<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Events;

/**
 * Marker: این رویداد باید به صف برود (نه اجرای همزمان).
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
interface ShouldQueue
{
}

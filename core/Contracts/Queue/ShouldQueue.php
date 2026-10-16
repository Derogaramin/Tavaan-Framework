<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Queue;

/**
 * تَوان | Tavaan — Contracts
 * ShouldQueue — Marker: این کار/اعلان/Mailable باید به صف برود (DOC-061 §3).
 *
 * تفاوت با `Tavaan\Core\Contracts\Events\ShouldQueue`:
 * آن marker برای «رویدادها» است (EventDispatcher به صف push می‌کند)؛
 * این marker برای Job/Notification/Mailable/Listenerهای صف‌روند.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
interface ShouldQueue
{
}

<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Events;

/**
 * Subscriber: کلاسی که نگاشت چند رویداد → متد را یک‌جا اعلام می‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
interface EventSubscriber
{
    /**
     * @return array<string, string|int> رویداد → متد (یا اولویت)
     */
    public function subscribe(DispatcherInterface $events): array;
}

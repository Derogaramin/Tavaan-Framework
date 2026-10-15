<?php

declare(strict_types=1);

namespace Tavaan\Core\Events\FrameworkEvents;

use Tavaan\Core\Events\Event;

/**
 * رویداد رخ‌دادن استثنا (توسط ExceptionHandler dispatch می‌شود).
 *
 * @api 1.0
 * @see docs/03-engines/error.md
 */
final class ExceptionOccurred extends Event
{
    public function __construct(public readonly \Throwable $exception)
    {
    }
}

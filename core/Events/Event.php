<?php

declare(strict_types=1);

namespace Tavaan\Core\Events;

use Tavaan\Core\Contracts\Events\StoppableEventInterface;

/**
 * تَوان | Tavaan — Core
 * Event — پایهٔ اختیاری رویدادها (بدون اجبار؛ هر object قابل dispatch است).
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
abstract class Event implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}

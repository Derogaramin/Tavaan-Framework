<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event;

/**
 * تَوان | Tavaan — Event
 * RegisteredListener — یک Listener ثبت‌شده با اولویت و ترتیب قطعی.
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
final readonly class RegisteredListener
{
    /**
     * @param callable|class-string|array{0: object|class-string, 1: string} $listener
     */
    public function __construct(
        public mixed $listener,
        public int $priority,
        public int $seq,
        public string $pattern,
    ) {
    }

    /** توصیف خوانا (برای `tvn event:list`). */
    public function describe(): string
    {
        $l = $this->listener;
        if (\is_string($l)) {
            return $l;
        }
        if (\is_array($l)) {
            return (\is_object($l[0]) ? $l[0]::class : $l[0]) . '@' . $l[1];
        }
        if ($l instanceof \Closure) {
            return 'Closure';
        }
        return \is_object($l) ? $l::class : 'callable';
    }
}

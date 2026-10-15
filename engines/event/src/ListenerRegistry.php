<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event;

/**
 * تَوان | Tavaan — Event
 * ListenerRegistry — ثبت و Resolve Listenerها (Exact + Wildcard + Hierarchy).
 *
 * ترتیب Resolve (DOC-033 §5.2):
 * 1. exact class-string → 2. exact event name → 3. wildcard trie →
 * 4. hierarchy (interface/parent) → ادغام + unique + sort(priority DESC, seq ASC).
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
final class ListenerRegistry
{
    /** @var array<string, list<RegisteredListener>> رویدادهای exact */
    private array $exact = [];

    private WildcardMatcher $wildcards;

    private int $seq = 0;

    private int $total = 0;

    public function __construct(private readonly int $maxListeners = 500)
    {
        $this->wildcards = new WildcardMatcher();
    }

    /**
     * @param callable|class-string|array{0: object|class-string, 1: string} $listener
     */
    public function add(string $event, mixed $listener, int $priority = 0): void
    {
        if ($this->total >= $this->maxListeners) {
            throw Exceptions\MaxListenersExceededException::for($event, $this->maxListeners);
        }

        $registered = new RegisteredListener($listener, $priority, ++$this->seq, $event);
        $this->total++;

        if (\str_contains($event, '*')) {
            $this->wildcards->add($event, $registered);

            return;
        }

        $this->exact[$event][] = $registered;
    }

    /**
     * همهٔ Listenerهای یک رویداد (object یا نام) — مرتب و یکتا.
     *
     * @return list<RegisteredListener>
     */
    public function listenersFor(object|string $event): array
    {
        $found = [];
        $names = $this->namesFor($event);

        foreach ($names as $name) {
            foreach ($this->exact[$name] ?? [] as $l) {
                $found[] = $l;
            }
        }

        $primaryName = \is_string($event) ? $event : $event::class;
        foreach ($this->wildcards->match($primaryName) as $l) {
            $found[] = $l;
        }

        $found = $this->unique($found);

        \usort($found, static fn (RegisteredListener $a, RegisteredListener $b): int
            => $b->priority <=> $a->priority ?: $a->seq <=> $b->seq);

        return $found;
    }

    public function hasListeners(object|string $event): bool
    {
        $names = $this->namesFor($event);
        foreach ($names as $name) {
            if (($this->exact[$name] ?? []) !== []) {
                return true;
            }
        }

        if ($this->wildcards->isEmpty()) {
            return false;
        }

        $primaryName = \is_string($event) ? $event : $event::class;

        return $this->wildcards->match($primaryName) !== [];
    }

    public function forget(string $event): void
    {
        if (\str_contains($event, '*')) {
            $removed = $this->exact[$event] ?? [];
            $this->wildcards->remove($event);
            $this->total -= \count($removed);

            return;
        }

        $this->total -= \count($this->exact[$event] ?? []);
        unset($this->exact[$event]);
    }

    public function flush(): void
    {
        $this->exact = [];
        $this->wildcards = new WildcardMatcher();
        $this->seq = 0;
        $this->total = 0;
    }

    public function count(): int
    {
        return $this->total;
    }

    /**
     * فهرست همهٔ رویدادها/الگوهای ثبت‌شده (برای `tvn event:list`).
     *
     * @return array<string, list<RegisteredListener>>
     */
    public function all(): array
    {
        $out = $this->exact;
        foreach ($this->wildcards->patterns() as $pattern) {
            $out[$pattern] = $this->wildcards->match(\str_replace('*', 'x', $pattern));
        }
        \ksort($out);

        return $out;
    }

    /**
     * نام‌های قابل تطبیق یک رویداد: کلاس + والدین + Interfaceها (Hierarchy).
     *
     * @return list<string>
     */
    private function namesFor(object|string $event): array
    {
        if (\is_string($event)) {
            return [$event];
        }

        $names = [$event::class];
        foreach (\class_parents($event) ?: [] as $parent) {
            $names[] = $parent;
        }
        foreach (\class_implements($event) ?: [] as $interface) {
            $names[] = $interface;
        }

        return $names;
    }

    /**
     * @param list<RegisteredListener> $listeners
     *
     * @return list<RegisteredListener>
     */
    private function unique(array $listeners): array
    {
        $seen = [];
        $out = [];
        foreach ($listeners as $l) {
            if (isset($seen[$l->seq])) {
                continue;
            }
            $seen[$l->seq] = true;
            $out[] = $l;
        }

        return $out;
    }
}

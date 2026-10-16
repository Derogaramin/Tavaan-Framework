<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime;

use Tavaan\Core\Contracts\Realtime\BrokerInterface;

/**
 * تَوان | Tavaan — Realtime
 * RealtimeFacadeRoot — ریشهٔ Facade «Realtime» (ترکیب manager + presence).
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class RealtimeFacadeRoot
{
    public function __construct(
        private readonly RealtimeManager $manager,
        private readonly PresenceManager $presence,
    ) {
    }

    /** فهرست حاضران کانال. @return list<array<string, mixed>> */
    public function presence(string $channel): array
    {
        return $this->presence->members($channel);
    }

    public function presenceCount(string $channel): int
    {
        return $this->presence->count($channel);
    }

    /** @param array<string, mixed> $member */
    public function join(string $channel, array $member): void
    {
        $this->presence->join($channel, $member);
    }

    public function leave(string $channel, string $memberId): void
    {
        $this->presence->leave($channel, $memberId);
    }

    public function broadcast(object $event): int
    {
        return $this->manager->broadcast($event);
    }

    /** @param array<string, mixed> $payload */
    public function publish(string $channel, string $name, array $payload): void
    {
        $this->manager->publish($channel, $name, $payload);
    }

    public function channel(string $pattern, \Closure $callback): void
    {
        $this->manager->channel($pattern, $callback);
    }

    public function broker(?string $name = null): BrokerInterface
    {
        return $this->manager->broker($name);
    }

    public function manager(): RealtimeManager
    {
        return $this->manager;
    }
}

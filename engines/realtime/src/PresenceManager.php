<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Realtime
 * PresenceManager — کاربران حاضر در کانال (DOC-045 §7).
 *
 * ذخیره در cache با TTL — عضویت با heartbeat تمدید می‌شود؛
 * خواندن، عضویت‌های منقضی را هرس می‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class PresenceManager
{
    public function __construct(
        private readonly ?CacheRepository $cache = null,
        private readonly int $ttl = 90,
    ) {
    }

    /**
     * عضویت/heartbeat.
     *
     * @param array<string, mixed> $member باید کلید 'id' داشته باشد
     */
    public function join(string $channel, array $member): void
    {
        if ($this->cache === null) {
            return;
        }
        $id = (string) ($member['id'] ?? '');
        if ($id === '') {
            return;
        }

        $members = $this->raw($channel);
        $members[$id] = [
            'data' => $member,
            'expires' => Clock::timestamp() + $this->ttl,
        ];
        $this->cache->forever($this->key($channel), $members);
    }

    public function leave(string $channel, string $memberId): void
    {
        if ($this->cache === null) {
            return;
        }
        $members = $this->raw($channel);
        unset($members[$memberId]);
        $this->cache->forever($this->key($channel), $members);
    }

    /**
     * فهرست حاضران (با هرس منقضی‌ها).
     *
     * @return list<array<string, mixed>>
     */
    public function members(string $channel): array
    {
        $members = $this->raw($channel);
        $now = Clock::timestamp();

        $alive = [];
        $pruned = false;
        foreach ($members as $id => $entry) {
            if ((int) ($entry['expires'] ?? 0) > $now) {
                $alive[(string) $id] = $entry;
            } else {
                $pruned = true;
            }
        }
        if ($pruned && $this->cache !== null) {
            $this->cache->forever($this->key($channel), $alive);
        }

        return \array_values(\array_map(static fn (array $e): array => (array) ($e['data'] ?? []), $alive));
    }

    public function count(string $channel): int
    {
        return \count($this->members($channel));
    }

    /** @return array<string, array<string, mixed>> */
    private function raw(string $channel): array
    {
        if ($this->cache === null) {
            return [];
        }
        /** @var mixed $members */
        $members = $this->cache->get($this->key($channel));

        return \is_array($members) ? $members : [];
    }

    private function key(string $channel): string
    {
        $safe = (string) \preg_replace('/[^A-Za-z0-9._:\-\/]/', '', $channel);

        return 'tvn:presence:' . $safe;
    }
}

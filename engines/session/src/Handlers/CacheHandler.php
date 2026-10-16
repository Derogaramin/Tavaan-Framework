<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session\Handlers;

use Tavaan\Core\Contracts\Cache\RepositoryInterface;
use Tavaan\Engine\Session\SessionHandlerInterface;

/**
 * تَوان | Tavaan — Session
 * CacheHandler — ذخیرهٔ نشست روی موتور cache (درایور redis — M4، تکمیل TVN-0022).
 *
 * 🔒 سریال‌سازی فقط JSON (موتور cache).
 * 🔒 TTL = lifetime نشست (انقضا خودکار سمت store).
 *
 * @api 1.0
 * @see docs/03-engines/session.md
 */
final class CacheHandler implements SessionHandlerInterface
{
    public function __construct(
        private readonly RepositoryInterface $cache,
        private readonly string $prefix = 'session:',
        private readonly int $lifetimeSeconds = 7200,
    ) {
    }

    /** @return array<string, mixed> */
    public function read(string $id): array
    {
        $value = $this->cache->get($this->prefix . $id);

        return \is_array($value) ? $value : [];
    }

    /** @param array<string, mixed> $data */
    public function write(string $id, array $data): bool
    {
        return $this->cache->put($this->prefix . $id, $data, $this->lifetimeSeconds);
    }

    public function destroy(string $id): bool
    {
        return $this->cache->forget($this->prefix . $id);
    }

    public function gc(int $maxLifetimeSeconds): int
    {
        return 0;   // انقضا خودکار توسط TTL در store
    }
}

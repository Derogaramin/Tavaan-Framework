<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session\Handlers;

use Tavaan\Engine\Session\SessionHandlerInterface;

/**
 * درایور حافظه (تست/CLI).
 *
 * @api 1.0
 * @see docs/03-engines/session.md
 */
final class ArrayHandler implements SessionHandlerInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $store = [];

    public function read(string $id): array
    {
        return $this->store[$id] ?? [];
    }

    public function write(string $id, array $data): bool
    {
        $this->store[$id] = $data;

        return true;
    }

    public function destroy(string $id): bool
    {
        unset($this->store[$id]);

        return true;
    }

    public function gc(int $maxLifetimeSeconds): int
    {
        return 0;
    }
}

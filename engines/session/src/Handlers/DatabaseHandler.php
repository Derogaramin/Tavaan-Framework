<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session\Handlers;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Engine\Session\SessionHandlerInterface;

/**
 * تَوان | Tavaan — Session
 * DatabaseHandler — ذخیرهٔ نشست در جدول sessions (M3 — تکمیل TVN-0022).
 *
 * 🔒 سریال‌سازی فقط JSON (Object Injection ممنوع).
 * 🔒 نوشتن با upsert (یک کوئری اتمیک).
 *
 * @api 1.0
 * @see docs/03-engines/session.md
 */
final class DatabaseHandler implements SessionHandlerInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'sessions',
    ) {
    }

    /** @return array<string, mixed> */
    public function read(string $id): array
    {
        $row = $this->connection->table($this->table)->where('id', $id)->first();
        if ($row === null) {
            return [];
        }

        $payload = (string) ($row['payload'] ?? '');
        if ($payload === '') {
            return [];
        }

        try {
            $decoded = \json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $data */
    public function write(string $id, array $data): bool
    {
        $payload = (string) \json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $now = \Tavaan\Support\Time\Clock::timestamp();

        $this->connection->table($this->table)->updateOrInsert(
            ['id' => $id],
            [
                'payload'    => $payload,
                'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => \substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'last_activity' => $now,
            ],
        );

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->connection->table($this->table)->where('id', $id)->delete();

        return true;
    }

    public function gc(int $maxLifetimeSeconds): int
    {
        $cutoff = \Tavaan\Support\Time\Clock::timestamp() - $maxLifetimeSeconds;

        return $this->connection->table($this->table)->where('last_activity', '<', $cutoff)->delete();
    }
}

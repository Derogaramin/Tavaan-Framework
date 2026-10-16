<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Devices;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Auth
 * DeviceManager — ردیابی نشست‌های فعال روی دستگاه‌ها (DOC-056 §4: devices).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class DeviceManager
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $table = 'user_devices',
    ) {
    }

    /** ثبت/به‌روزرسانی دستگاه جاری (در login). */
    public function record(Authenticatable $user, ?string $ip = null, ?string $userAgent = null): int|string
    {
        $fingerprint = \hash('sha256', ($ip ?? '') . '|' . ($userAgent ?? ''));
        $now = Clock::utcNow()->format('Y-m-d H:i:s');

        $existing = $this->db->table($this->table)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($existing !== null) {
            $this->db->table($this->table)
                ->where('id', $existing['id'])
                ->update(['last_used_at' => $now]);

            return $existing['id'];
        }

        return $this->db->table($this->table)->insertGetId([
            'user_id' => (string) $user->getAuthIdentifier(),
            'fingerprint' => $fingerprint,
            'ip_address' => (string) ($ip ?? ''),
            'user_agent' => \substr((string) ($userAgent ?? ''), 0, 255),
            'revoked' => 0,
            'last_used_at' => $now,
            'created_at' => $now,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function list(Authenticatable $user): array
    {
        return $this->db->table($this->table)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->where('revoked', 0)
            ->orderByDesc('last_used_at')
            ->get();
    }

    public function revoke(int|string $deviceId, Authenticatable $user): bool
    {
        $affected = $this->db->table($this->table)
            ->where('id', $deviceId)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->update(['revoked' => 1]);

        return $affected > 0;
    }

    public function revokeAll(Authenticatable $user): int
    {
        return $this->db->table($this->table)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->update(['revoked' => 1]);
    }
}

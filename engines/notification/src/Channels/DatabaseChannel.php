<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Channels;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\Notification\Exceptions\NotificationException;
use Tavaan\Engine\Notification\Notification;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Notification
 * DatabaseChannel — آگاه‌سازی In-App در جدول tvn_notifications (DOC-066 §4).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class DatabaseChannel implements ChannelInterface
{
    public function __construct(
        private readonly ?ConnectionInterface $db = null,
        private readonly string $table = 'tvn_notifications',
    ) {
    }

    public function send(Notifiable $notifiable, mixed $message, Notification $notification): mixed
    {
        if ($this->db === null) {
            throw NotificationException::channelUnavailable('database');
        }

        if (!\is_array($message)) {
            throw NotificationException::invalidMessage('database', 'array');
        }

        $id = $notification->id;

        $this->db->table($this->table)->insert([
            'id' => $id,
            'type' => $notification::class,
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => (string) $notifiable->getNotificationKey(),
            'data' => \json_encode($message, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            'read_at' => null,
            'created_at' => Clock::now()->format('Y-m-d H:i:s.v'),
        ]);

        return $id;
    }

    public function name(): string
    {
        return 'database';
    }

    // ─── API خواندن In-App (DOC-066 §4) ───────────────────────────────────

    /**
     * آگاه‌سازی‌های یک نوتیفای‌شونده.
     *
     * @return list<array<string, mixed>>
     */
    public function allFor(Notifiable $notifiable, bool $unreadOnly = false, int $limit = 100): array
    {
        if ($this->db === null) {
            return [];
        }

        $query = $this->db->table($this->table)
            ->where('notifiable_type', $notifiable::class)
            ->where('notifiable_id', (string) $notifiable->getNotificationKey());

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();

        return \array_map(static function (array $row): array {
            $data = \json_decode((string) ($row['data'] ?? '{}'), true);
            $row['data'] = \is_array($data) ? $data : [];

            return $row;
        }, $rows);
    }

    /** تعداد خوانده‌نشده‌ها. */
    public function unreadCount(Notifiable $notifiable): int
    {
        if ($this->db === null) {
            return 0;
        }

        return $this->db->table($this->table)
            ->where('notifiable_type', $notifiable::class)
            ->where('notifiable_id', (string) $notifiable->getNotificationKey())
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(string $id): bool
    {
        if ($this->db === null) {
            return false;
        }

        return $this->db->table($this->table)
                ->where('id', $id)
                ->whereNull('read_at')
                ->update(['read_at' => Clock::now()->format('Y-m-d H:i:s.v')]) > 0;
    }

    public function markAllAsRead(Notifiable $notifiable): int
    {
        if ($this->db === null) {
            return 0;
        }

        return $this->db->table($this->table)
            ->where('notifiable_type', $notifiable::class)
            ->where('notifiable_id', (string) $notifiable->getNotificationKey())
            ->whereNull('read_at')
            ->update(['read_at' => Clock::now()->format('Y-m-d H:i:s.v')]);
    }

    public function deleteFor(Notifiable $notifiable): int
    {
        if ($this->db === null) {
            return 0;
        }

        return $this->db->table($this->table)
            ->where('notifiable_type', $notifiable::class)
            ->where('notifiable_id', (string) $notifiable->getNotificationKey())
            ->delete();
    }
}

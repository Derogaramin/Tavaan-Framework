<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ForumBroadcaster — realtime موضوع/پست جدید (DOC-094 §5/§6 + E2E-06).
 */

namespace Modules\Forum\Infrastructure\Realtime;

use Tavaan\Core\Facade\Facades\Broadcast;

/**
 * ForumBroadcaster — شنوندهٔ `forum.thread.created` و `forum.post.created`.
 *
 * کانال‌ها:
 * - `forum` (سراسری — فهرست آخرین فعالیت)
 * - `forum.thread.{ULID}` (صفحهٔ موضوع — AC-FORUM-08: مشترکین SSE
 *   موتور realtime از راه `GET /events/stream?channels=forum.thread.{id}`
 *   پست تازه را بی‌درنگ می‌گیرند — بدون ext).
 *
 * best-effort: خطای realtime جریان نوشتن را نمی‌شکند.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ForumBroadcaster
{
    /**
     * @param array<string, mixed> $config کلید config('forum.realtime')
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function onThreadCreated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $lite = [
            'thread_id' => (string) ($payload['thread_id'] ?? ''),
            'title' => (string) ($payload['title'] ?? ''),
            'slug' => (string) ($payload['slug'] ?? ''),
            'author_id' => (int) ($payload['author_id'] ?? 0),
            'url' => '/threads/' . (string) ($payload['slug'] ?? ''),
        ];

        $this->publish($this->globalChannel(), 'thread.created', $lite);
        $this->publish($this->threadChannel((string) ($payload['thread_id'] ?? '')), 'thread.created', $lite);
    }

    public function onPostCreated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $threadId = (string) ($payload['thread_id'] ?? '');
        if ($threadId === '') {
            return;
        }

        $body = \trim((string) \preg_replace('/\s+/u', ' ', \strip_tags((string) ($payload['body'] ?? ''))));
        $lite = [
            'post_id' => (string) ($payload['post_id'] ?? ''),
            'thread_id' => $threadId,
            'author_id' => (int) ($payload['author_id'] ?? 0),
            'excerpt' => \mb_substr($body, 0, 160),
            'is_first' => (bool) ($payload['is_first'] ?? false),
            'url' => '/threads/' . (string) ($payload['thread_slug'] ?? $threadId),
        ];

        $this->publish($this->threadChannel($threadId), 'post.created', $lite);
        if (!$lite['is_first']) {
            $this->publish($this->globalChannel(), 'post.created', $lite);
        }
    }

    private function publish(string $channel, string $name, array $payload): void
    {
        if (!$this->enabled() || $channel === '') {
            return;
        }

        try {
            Broadcast::publish($channel, $name, $payload);
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    private function globalChannel(): string
    {
        return (string) ($this->config['channel'] ?? 'forum');
    }

    private function threadChannel(string $threadId): string
    {
        return (string) ($this->config['thread_channel_prefix'] ?? 'forum.thread.') . $threadId;
    }
}

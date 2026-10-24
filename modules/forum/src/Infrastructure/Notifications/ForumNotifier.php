<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ForumNotifier — اعلان‌های انجمن (DOC-094 §5/§6).
 */

namespace Modules\Forum\Infrastructure\Notifications;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Forum\Application\UseCases\ProfileService;
use Modules\Forum\Domain\Repositories\SubscriptionRepositoryInterface;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Lang;
use Tavaan\Core\Facade\Facades\Notification;

/**
 * ForumNotifier — شنوندهٔ رویدادهای انجمن:
 *
 * - `forum.post.created` → نویسندهٔ موضوع + دنبال‌کنندگان + mentionها
 * - `forum.post.solved`  → نویسندهٔ پاسخ برگزیده
 * - `forum.post.flagged` → moderatorها (صف moderation)
 * - `forum.thread.created` → moderatorها
 *
 * ساخت پیام از binding `notification.message` (بدون ارجاع به موتور — ARCH-003).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ForumNotifier
{
    /**
     * @param array<string, mixed> $config کلید config('forum.notifications')
     */
    public function __construct(
        private readonly ThreadRepositoryInterface $threads,
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly UserReaderInterface $users,
        private readonly ProfileService $profiles,
        private readonly ModeratorFinder $moderators,
        private readonly ContainerInterface $container,
        private readonly array $config = [],
    ) {
    }

    /** پاسخ جدید → نویسندهٔ موضوع + دنبال‌کنندگان + mentionها. */
    public function onPostCreated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if ((bool) ($payload['is_first'] ?? false)) {
            return;   // پست اول «پاسخ» نیست — اعلان پاسخ ندارد
        }

        $thread = $this->threads->findById((string) ($payload['thread_id'] ?? ''));
        if ($thread === null) {
            return;
        }

        $posterId = (int) ($payload['author_id'] ?? 0);
        $excerpt = $this->excerpt((string) ($payload['body'] ?? ''));
        $url = '/threads/' . $thread->slug();

        $recipients = [];

        if ((bool) ($this->config['reply_to_author'] ?? true)) {
            $recipients[] = $thread->authorId();
        }
        if ((bool) ($this->config['reply_to_subscribers'] ?? true)) {
            foreach ($this->subscriptions->subscribersOf($thread->id()) as $subscriberId) {
                $recipients[] = $subscriberId;
            }
        }

        $mentionIds = [];
        if ((bool) ($this->config['mention'] ?? true)) {
            foreach ((array) ($payload['mentions'] ?? []) as $mentionId) {
                $mentionIds[] = (int) $mentionId;
            }
        }

        $posterName = $this->profiles->usernameOf($posterId);

        // اعلان پاسخ (بدون mentionها — آنها kind جدا دارند)
        $seen = [];
        foreach ($recipients as $recipientId) {
            if ($recipientId === $posterId || isset($seen[$recipientId]) || \in_array($recipientId, $mentionIds, true)) {
                continue;
            }
            $seen[$recipientId] = true;
            $this->notify($recipientId, 'reply', [
                'title' => Lang::get('forum::messages.notify_reply_subject', ['thread' => $thread->title()]),
                'line' => Lang::get('forum::messages.notify_reply_line', ['user' => $posterName, 'excerpt' => $excerpt]),
                'url' => $url,
                'database' => ['kind' => 'forum.post.reply', 'thread_id' => $thread->id(), 'post_id' => (string) ($payload['post_id'] ?? '')],
            ]);
        }

        // اعلان mention (@username)
        foreach ($mentionIds as $mentionId) {
            if ($mentionId === $posterId) {
                continue;
            }
            $this->notify($mentionId, 'mention', [
                'title' => Lang::get('forum::messages.notify_mention_subject', ['thread' => $thread->title()]),
                'line' => Lang::get('forum::messages.notify_mention_line', ['user' => $posterName]),
                'url' => $url,
                'database' => ['kind' => 'forum.post.mention', 'thread_id' => $thread->id(), 'post_id' => (string) ($payload['post_id'] ?? '')],
            ]);
        }
    }

    /** پاسخ برگزیده → نویسندهٔ پست. */
    public function onPostSolved(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if (!(bool) ($payload['marked'] ?? false) || !(bool) ($this->config['solution'] ?? true)) {
            return;
        }

        $postAuthorId = (int) ($payload['post_author_id'] ?? 0);
        $actorId = (int) ($payload['actor_id'] ?? 0);
        if ($postAuthorId === 0 || $postAuthorId === $actorId) {
            return;
        }

        $thread = $this->threads->findById((string) ($payload['thread_id'] ?? ''));
        $this->notify($postAuthorId, 'solution', [
            'title' => Lang::get('forum::messages.notify_solution_subject'),
            'line' => Lang::get('forum::messages.notify_solution_line', ['thread' => $thread?->title() ?? '']),
            'url' => '/threads/' . ($thread?->slug() ?? ''),
            'database' => ['kind' => 'forum.post.solution', 'thread_id' => (string) ($payload['thread_id'] ?? ''), 'post_id' => (string) ($payload['post_id'] ?? '')],
        ]);
    }

    /** گزارش پست → moderatorها. */
    public function onPostFlagged(string $event, mixed $payload): void
    {
        $this->notifyModerators('flagged', (array) $payload);
    }

    /** موضوع جدید → moderatorها (DOC-094 §5). */
    public function onThreadCreated(string $event, mixed $payload): void
    {
        if (!(bool) ($this->config['thread_created_to_moderators'] ?? true)) {
            return;
        }

        $payload = (array) $payload;
        foreach ($this->moderators->userIds() as $moderatorId) {
            $this->notify($moderatorId, 'thread', [
                'title' => Lang::get('forum::messages.notify_thread_subject'),
                'line' => Lang::get('forum::messages.notify_thread_line', ['title' => (string) ($payload['title'] ?? '')]),
                'url' => '/threads/' . (string) ($payload['slug'] ?? ''),
                'database' => ['kind' => 'forum.thread.created', 'thread_id' => (string) ($payload['thread_id'] ?? '')],
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function notifyModerators(string $kind, array $payload): void
    {
        if (!(bool) ($this->config['flagged_to_moderators'] ?? true)) {
            return;
        }

        foreach ($this->moderators->userIds() as $moderatorId) {
            $this->notify($moderatorId, $kind, [
                'title' => Lang::get('forum::messages.notify_flag_subject'),
                'line' => Lang::get('forum::messages.notify_flag_line', ['reason' => (string) ($payload['reason'] ?? '')]),
                'url' => '/admin/forum/moderation',
                'database' => ['kind' => 'forum.post.flagged', 'post_id' => (string) ($payload['post_id'] ?? ''), 'flag_id' => (int) ($payload['flag_id'] ?? 0)],
            ]);
        }
    }

    /** @param array{title: string, line: string, url: string, database: array<string, mixed>} $message */
    private function notify(int $userId, string $kind, array $message): void
    {
        try {
            $notifiable = $this->users->findNotifiable($userId);
            if ($notifiable === null) {
                return;
            }

            $notification = $this->makeNotification([
                'via' => ['mail', 'database'],
                'mail' => [
                    'subject' => $message['title'],
                    'lines' => [$message['line']],
                    'action' => ['text' => Lang::get('forum::messages.notify_cta'), 'url' => $message['url']],
                ],
                'database' => $message['database'],
            ]);

            if ($notification !== null) {
                Notification::send($notifiable, $notification);
            }
        } catch (\Throwable) {
            // best-effort
        }
    }

    /** @param array<string, mixed> $payload */
    private function makeNotification(array $payload): ?object
    {
        if (!$this->container->bound('notification.message')) {
            return null;
        }

        $notification = $this->container->make('notification.message', ['payload' => $payload]);

        return \is_object($notification) ? $notification : null;
    }

    private function excerpt(string $rawBody): string
    {
        $text = \trim((string) \preg_replace('/\s+/u', ' ', \strip_tags($rawBody)));

        return \mb_substr($text, 0, 120);
    }
}

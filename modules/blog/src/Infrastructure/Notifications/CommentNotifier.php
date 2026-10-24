<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * CommentNotifier — اعلان‌های نظر (DOC-091 §5: notification).
 */

namespace Modules\Blog\Infrastructure\Notifications;

use Modules\Blog\Domain\Repositories\PostRepositoryInterface;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Tavaan\Core\Facade\Facades\Lang;
use Tavaan\Core\Facade\Facades\Notification;

/**
 * CommentNotifier — شنوندهٔ `blog.comment.submitted|approved`.
 *
 * - submitted → اعلان به نویسندهٔ مقاله (Notifiable از core-user)
 * - approved  → اعلان به نویسندهٔ نظر (AnonymousNotifiable از راه ایمیل)
 *
 * 🔒 ARCH-003: ساخت پیام اعلان از binding کانتینر `notification.message`
 * (ArrayNotification موتور notification) — بدون ارجاع کلاسی به موتور.
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class CommentNotifier
{
    /**
     * @param array<string, mixed> $config کلید config('blog.comments')
     */
    public function __construct(
        private readonly PostRepositoryInterface $posts,
        private readonly UserReaderInterface $users,
        private readonly \Tavaan\Core\Contracts\Container\ContainerInterface $container,
        private readonly array $config = [],
    ) {
    }

    /** قرارداد listener رویداد رشته‌ای: ($eventName, $payload). */
    public function onSubmitted(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if (!(bool) ($this->config['notify_author'] ?? true)) {
            return;
        }

        try {
            $post = $this->posts->findById((string) ($payload['post_id'] ?? ''));
            if ($post === null) {
                return;
            }

            $author = $this->users->findNotifiable($post->authorId());
            if ($author === null) {
                return;
            }

            $subject = Lang::get('blog::messages.notify_author_subject', ['title' => $post->title()]);
            $notification = $this->makeNotification([
                'via' => ['mail', 'database'],
                'mail' => [
                    'subject' => $subject,
                    'lines' => [
                        Lang::get('blog::messages.notify_author_line', [
                            'name' => (string) ($payload['author_name'] ?? ''),
                            'title' => $post->title(),
                        ]),
                    ],
                    'action' => [
                        'text' => Lang::get('blog::messages.notify_author_cta'),
                        'url' => '/blog/' . $post->slug() . '#comments',
                    ],
                ],
                'database' => [
                    'kind' => 'blog.comment.submitted',
                    'post_id' => $post->id(),
                    'comment_id' => (string) ($payload['comment_id'] ?? ''),
                    'status' => (string) ($payload['status'] ?? 'pending'),
                ],
            ]);

            if ($notification !== null) {
                Notification::send($author, $notification);
            }
        } catch (\Throwable) {
            // best-effort — اعلان جریان ثبت نظر را نمی‌شکند
        }
    }

    public function onApproved(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if (!(bool) ($this->config['notify_commenter'] ?? true)) {
            return;
        }

        $email = (string) ($payload['commenter_email'] ?? '');
        if ($email === '' || !\str_contains($email, '@')) {
            return;
        }

        try {
            $post = $this->posts->findById((string) ($payload['post_id'] ?? ''));
            $notification = $this->makeNotification([
                'via' => ['mail'],
                'mail' => [
                    'subject' => Lang::get('blog::messages.notify_commenter_subject'),
                    'greeting' => Lang::get('blog::messages.notify_commenter_greeting', [
                        'name' => (string) ($payload['commenter_name'] ?? ''),
                    ]),
                    'lines' => [Lang::get('blog::messages.notify_commenter_line')],
                    'action' => $post === null ? null : [
                        'text' => Lang::get('blog::messages.notify_commenter_cta'),
                        'url' => '/blog/' . $post->slug() . '#comments',
                    ],
                ],
            ]);

            if ($notification !== null) {
                Notification::route('mail', $email)->notify($notification);
            }
        } catch (\Throwable) {
            // best-effort
        }
    }

    /**
     * ساخت اعلان از binding کانتینر (بدون ارجاع به کلاس موتور).
     *
     * @param array<string, mixed> $payload
     */
    private function makeNotification(array $payload): ?object
    {
        if (!$this->container->bound('notification.message')) {
            return null;
        }

        $notification = $this->container->make('notification.message', ['payload' => $payload]);

        return \is_object($notification) ? $notification : null;
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * WorkflowNotifier — اعلان‌های workflow به نویسنده (DOC-092 §3).
 */

namespace Modules\News\Infrastructure\Notifications;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\News\Domain\Repositories\ArticleRepositoryInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Lang;
use Tavaan\Core\Facade\Facades\Notification;

/**
 * WorkflowNotifier — شنوندهٔ `news.article.approved|rejected|published`.
 *
 * گیرنده = نویسندهٔ خبر (UserReaderInterface::findNotifiable از core-user).
 * ساخت پیام از binding `notification.message` (بدون ارجاع به موتور — ARCH-003).
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class WorkflowNotifier
{
    /**
     * @param array<string, mixed> $config کلید config('news.notifications')
     */
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly UserReaderInterface $users,
        private readonly ContainerInterface $container,
        private readonly array $config = [],
    ) {
    }

    public function onApproved(string $event, mixed $payload): void
    {
        $this->notifyAuthor((array) $payload, 'approved');
    }

    public function onRejected(string $event, mixed $payload): void
    {
        $this->notifyAuthor((array) $payload, 'rejected');
    }

    public function onPublished(string $event, mixed $payload): void
    {
        $this->notifyAuthor((array) $payload, 'published');
    }

    /** @param array<string, mixed> $payload */
    private function notifyAuthor(array $payload, string $kind): void
    {
        if (!(bool) ($this->config['workflow_' . $kind] ?? true)) {
            return;
        }

        try {
            $article = $this->articles->findById((string) ($payload['article_id'] ?? ''));
            if ($article === null) {
                return;
            }
            $author = $this->users->findNotifiable($article->authorId());
            if ($author === null) {
                return;
            }

            $title = (string) ($payload['title'] ?? $article->title());
            $notification = $this->makeNotification([
                'via' => ['mail', 'database'],
                'mail' => [
                    'subject' => Lang::get('news::messages.notify_' . $kind . '_subject', ['title' => $title]),
                    'lines' => [
                        Lang::get('news::messages.notify_' . $kind . '_line', [
                            'title' => $title,
                            'reason' => (string) ($payload['reason'] ?? ''),
                        ]),
                    ],
                    'action' => [
                        'text' => Lang::get('news::messages.notify_cta'),
                        'url' => '/news/' . $article->slug(),
                    ],
                ],
                'database' => [
                    'kind' => 'news.article.' . $kind,
                    'article_id' => $article->id(),
                    'editor_id' => $payload['editor_id'] ?? null,
                ],
            ]);

            if ($notification !== null) {
                Notification::send($author, $notification);
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
}

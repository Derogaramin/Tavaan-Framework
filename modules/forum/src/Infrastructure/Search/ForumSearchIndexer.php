<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ForumSearchIndexer — ایندکس جست‌وجوی فارسی از رویدادها (DOC-094 §5/§6).
 */

namespace Modules\Forum\Infrastructure\Search;

use Modules\Forum\Domain\Repositories\PostRepositoryInterface;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Tavaan\Core\Facade\Facades\Search;

/**
 * ForumSearchIndexer — شنوندهٔ رویدادهای thread/post.
 *
 * دو ایندکس: `forum_threads` (عنوان + بدنهٔ پست اول) و `forum_posts`
 * (بدنهٔ پاسخ‌ها). best-effort: خطای ایندکس جریان اصلی را نمی‌شکند.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ForumSearchIndexer
{
    /**
     * @param array<string, mixed> $config کلید config('forum.search')
     */
    public function __construct(
        private readonly ThreadRepositoryInterface $threads,
        private readonly PostRepositoryInterface $posts,
        private readonly array $config = [],
    ) {
    }

    public function onThreadCreated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $this->indexThread((string) ($payload['thread_id'] ?? ''));
    }

    public function onThreadUpdated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $id = (string) ($payload['thread_id'] ?? '');

        if (($payload['change'] ?? '') === 'delete') {
            $this->removeThread($id);

            return;
        }
        $this->indexThread($id);
    }

    public function onPostCreated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if ((bool) ($payload['is_first'] ?? false)) {
            // بدنهٔ پست اول در ایندکس موضوع می‌نشیند
            $this->indexThread((string) ($payload['thread_id'] ?? ''));

            return;
        }
        $this->indexPost((string) ($payload['post_id'] ?? ''));
    }

    public function onPostUpdated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $this->indexPost((string) ($payload['post_id'] ?? ''));
    }

    public function onPostHidden(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $this->removePost((string) ($payload['post_id'] ?? ''));
    }

    public function onPostDeleted(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $this->removePost((string) ($payload['post_id'] ?? ''));
    }

    public function onPostSolved(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $this->indexThread((string) ($payload['thread_id'] ?? ''));
    }

    private function indexThread(string $threadId): void
    {
        if (!$this->enabled() || $threadId === '') {
            return;
        }

        try {
            $thread = $this->threads->findById($threadId);
            if ($thread === null) {
                return;
            }

            $first = $this->posts->firstPostOfThread($threadId);
            Search::index($this->threadsIndex(), [
                'id' => $thread->id(),
                'title' => $thread->title(),
                'slug' => $thread->slug(),
                'body' => $first !== null ? \strip_tags($first->body()) : '',
                'forum_id' => $thread->forumId(),
                'author_id' => $thread->authorId(),
                'is_solved' => $thread->isSolved(),
                'last_post_at' => $thread->lastPostAt()?->format('Y-m-d H:i:s'),
            ], $thread->id());
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function indexPost(string $postId): void
    {
        if (!$this->enabled() || $postId === '') {
            return;
        }

        try {
            $post = $this->posts->findById($postId);
            if ($post === null || !$post->isVisible()) {
                return;
            }

            Search::index($this->postsIndex(), [
                'id' => $post->id(),
                'thread_id' => $post->threadId(),
                'author_id' => $post->authorId(),
                'body' => \strip_tags($post->body()),
                'is_solution' => $post->isSolution(),
                'created_at' => $post->createdAt()->format('Y-m-d H:i:s'),
            ], $post->id());
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function removeThread(string $threadId): void
    {
        if (!$this->enabled() || $threadId === '') {
            return;
        }

        try {
            Search::delete($this->threadsIndex(), $threadId);
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function removePost(string $postId): void
    {
        if (!$this->enabled() || $postId === '') {
            return;
        }

        try {
            Search::delete($this->postsIndex(), $postId);
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    private function threadsIndex(): string
    {
        return (string) ($this->config['threads_index'] ?? 'forum_threads');
    }

    private function postsIndex(): string
    {
        return (string) ($this->config['posts_index'] ?? 'forum_posts');
    }
}

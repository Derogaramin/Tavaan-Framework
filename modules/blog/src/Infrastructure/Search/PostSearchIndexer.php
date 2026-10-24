<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * PostSearchIndexer — ایندکس جست‌وجو از رویدادهای انتشار/ویرایش/حذف (DOC-091 §5).
 */

namespace Modules\Blog\Infrastructure\Search;

use Modules\Blog\Domain\Repositories\PostRepositoryInterface;
use Tavaan\Core\Facade\Facades\Search;

/**
 * PostSearchIndexer — شنوندهٔ `blog.post.published|updated|deleted`.
 *
 * مصرف‌کنندهٔ رویداد مطابق DOC-091 §5: search (ایندکس مقالهٔ منتشرشده) و
 * حذف ایندکس در unpublish/delete. خطاها بلعیده می‌شوند (ایندکس best-effort
 * است؛ جریان اصلی هرگز نباید بشکند).
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class PostSearchIndexer
{
    /**
     * @param array<string, mixed> $config کلید config('blog.search')
     */
    public function __construct(
        private readonly PostRepositoryInterface $posts,
        private readonly array $config = [],
    ) {
    }

    /** قرارداد listener رویداد رشته‌ای: ($eventName, $payload). */
    public function onPublished(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $this->index((string) ($payload['post_id'] ?? ''));
    }

    public function onUpdated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $id = (string) ($payload['post_id'] ?? '');
        if (($payload['status'] ?? '') === 'published') {
            $this->index($id);

            return;
        }
        $this->remove($id);
    }

    public function onDeleted(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $this->remove((string) ($payload['post_id'] ?? ''));
    }

    private function index(string $postId): void
    {
        if (!$this->enabled() || $postId === '') {
            return;
        }

        try {
            $post = $this->posts->findById($postId);
            if ($post === null || !$post->isPublished()) {
                return;
            }

            Search::index($this->indexName(), [
                'id' => $post->id(),
                'title' => $post->title(),
                'slug' => $post->slug(),
                'excerpt' => (string) $post->excerpt(),
                'body' => \strip_tags((string) $post->body()),
                'category_id' => $post->categoryId(),
                'author_id' => $post->authorId(),
                'published_at' => $post->publishedAt()?->format('Y-m-d H:i:s'),
            ], $post->id());
        } catch (\Throwable) {
            // best-effort — جست‌وجو جریان اصلی را نمی‌شکند
        }
    }

    private function remove(string $postId): void
    {
        if (!$this->enabled() || $postId === '') {
            return;
        }

        try {
            Search::delete($this->indexName(), $postId);
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    private function indexName(): string
    {
        return (string) ($this->config['index'] ?? 'blog_posts');
    }
}

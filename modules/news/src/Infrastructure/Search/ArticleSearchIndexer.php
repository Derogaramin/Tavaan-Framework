<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * ArticleSearchIndexer — ایندکس جست‌وجو از رویدادهای انتشار/ویرایش/حذف.
 */

namespace Modules\News\Infrastructure\Search;

use Modules\News\Domain\Repositories\ArticleRepositoryInterface;
use Tavaan\Core\Facade\Facades\Search;

/**
 * ArticleSearchIndexer — شنوندهٔ `news.article.published|updated|deleted`.
 * best-effort: خطای ایندکس جریان اصلی را نمی‌شکند.
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class ArticleSearchIndexer
{
    /**
     * @param array<string, mixed> $config کلید config('news.search')
     */
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly array $config = [],
    ) {
    }

    /** قرارداد listener رویداد رشته‌ای: ($eventName, $payload). */
    public function onPublished(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $this->index((string) ($payload['article_id'] ?? ''));
    }

    public function onUpdated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $id = (string) ($payload['article_id'] ?? '');
        if (($payload['status'] ?? '') === 'published') {
            $this->index($id);

            return;
        }
        $this->remove($id);
    }

    public function onDeleted(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        $this->remove((string) ($payload['article_id'] ?? ''));
    }

    private function index(string $articleId): void
    {
        if (!$this->enabled() || $articleId === '') {
            return;
        }

        try {
            $article = $this->articles->findById($articleId);
            if ($article === null || !$article->isPublished()) {
                return;
            }

            Search::index($this->indexName(), [
                'id' => $article->id(),
                'title' => $article->title(),
                'slug' => $article->slug(),
                'lead' => (string) $article->lead(),
                'body' => \strip_tags((string) $article->body()),
                'category_id' => $article->categoryId(),
                'author_id' => $article->authorId(),
                'is_breaking' => $article->isBreaking(),
                'published_at' => $article->publishedAt()?->format('Y-m-d H:i:s'),
            ], $article->id());
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function remove(string $articleId): void
    {
        if (!$this->enabled() || $articleId === '') {
            return;
        }

        try {
            Search::delete($this->indexName(), $articleId);
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
        return (string) ($this->config['index'] ?? 'news_articles');
    }
}

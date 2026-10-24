<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * FeedService — فید RSS/Atom از آخرین مقالات منتشرشده (DOC-091 §3/§6).
 */

namespace Modules\Blog\Application\UseCases;

use Modules\Blog\Domain\Repositories\PostRepositoryInterface;
use Modules\Content\Domain\Services\ExcerptGenerator;
use Modules\Content\Domain\Services\FeedBuilder;
use Modules\Content\Domain\ValueObjects\FeedItem;
use Modules\Content\Domain\ValueObjects\FeedMeta;
use Modules\CoreUser\Contracts\UserReaderInterface;

/**
 * @api 1.0
 * @see modules/blog/README.md
 */
final class FeedService
{
    /**
     * @param array<string, mixed> $config کلید config('blog.feed')
     */
    public function __construct(
        private readonly PostRepositoryInterface $posts,
        private readonly FeedBuilder $feedBuilder,
        private readonly ExcerptGenerator $excerpts,
        private readonly UserReaderInterface $users,
        private readonly array $config = [],
    ) {
    }

    /** @param list<FeedItem>|null $items — تزریق برای تست (null → از مخزن) */
    public function rss(string $siteUrl, ?array $items = null): string
    {
        return $this->feedBuilder->rss($this->meta($siteUrl, 'rss'), $items ?? $this->items($siteUrl));
    }

    public function atom(string $siteUrl, ?array $items = null): string
    {
        return $this->feedBuilder->atom($this->meta($siteUrl, 'atom'), $items ?? $this->items($siteUrl));
    }

    /** @return list<FeedItem> */
    public function items(string $siteUrl): array
    {
        $limit = (int) ($this->config['limit'] ?? 20);
        $posts = $this->posts->latestPublished($limit);

        $authorIds = [];
        foreach ($posts as $post) {
            $authorIds[] = (string) $post->authorId();
        }
        $authors = $authorIds === [] ? [] : $this->users->findByIds($authorIds);

        $items = [];
        foreach ($posts as $post) {
            $author = $authors[(string) $post->authorId()] ?? null;
            $items[] = new FeedItem(
                title: $post->title(),
                url: \rtrim($siteUrl, '/') . '/blog/' . $post->slug(),
                guid: 'blog-post-' . $post->id(),
                summary: $post->excerpt() ?? $this->excerpts->fromHtml((string) $post->body(), 160),
                publishedAt: $post->publishedAt() ?? $post->createdAt() ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                authorName: $author?->name ?? '',
                authorEmail: $author?->email ?? '',
            );
        }

        return $items;
    }

    private function meta(string $siteUrl, string $kind): FeedMeta
    {
        $base = \rtrim($siteUrl, '/');

        return new FeedMeta(
            title: (string) ($this->config['title'] ?? 'وبلاگ'),
            description: (string) ($this->config['description'] ?? ''),
            siteUrl: $base . '/blog',
            feedUrl: $base . '/blog/feed' . ($kind === 'atom' ? '?format=atom' : ''),
            language: (string) ($this->config['language'] ?? 'fa'),
        );
    }
}

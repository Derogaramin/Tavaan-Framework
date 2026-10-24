<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * FeedService — فید RSS/Atom اخبار (DOC-092 §6).
 */

namespace Modules\News\Application\UseCases;

use Modules\Content\Domain\Services\ExcerptGenerator;
use Modules\Content\Domain\Services\FeedBuilder;
use Modules\Content\Domain\ValueObjects\FeedItem;
use Modules\Content\Domain\ValueObjects\FeedMeta;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\News\Domain\Repositories\ArticleRepositoryInterface;

/**
 * @api 1.0
 * @see modules/news/README.md
 */
final class FeedService
{
    /**
     * @param array<string, mixed> $config کلید config('news.feed')
     */
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly FeedBuilder $feedBuilder,
        private readonly ExcerptGenerator $excerpts,
        private readonly UserReaderInterface $users,
        private readonly array $config = [],
    ) {
    }

    public function rss(string $siteUrl): string
    {
        return $this->feedBuilder->rss($this->meta($siteUrl, 'rss'), $this->items($siteUrl));
    }

    public function atom(string $siteUrl): string
    {
        return $this->feedBuilder->atom($this->meta($siteUrl, 'atom'), $this->items($siteUrl));
    }

    /** @return list<FeedItem> */
    public function items(string $siteUrl): array
    {
        $limit = (int) ($this->config['limit'] ?? 30);
        $articles = $this->articles->latestPublished($limit);

        $authorIds = [];
        foreach ($articles as $article) {
            $authorIds[] = (string) $article->authorId();
        }
        $authors = $authorIds === [] ? [] : $this->users->findByIds($authorIds);

        $items = [];
        foreach ($articles as $article) {
            $author = $authors[(string) $article->authorId()] ?? null;
            $items[] = new FeedItem(
                title: $article->title(),
                url: \rtrim($siteUrl, '/') . '/news/' . $article->slug(),
                guid: 'news-article-' . $article->id(),
                summary: $article->lead() ?? $this->excerpts->fromHtml((string) $article->body(), 160),
                publishedAt: $article->publishedAt() ?? $article->createdAt() ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
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
            title: (string) ($this->config['title'] ?? 'خبرگزاری'),
            description: (string) ($this->config['description'] ?? ''),
            siteUrl: $base . '/news',
            feedUrl: $base . '/news/feed' . ($kind === 'atom' ? '?format=atom' : ''),
            language: (string) ($this->config['language'] ?? 'fa'),
        );
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * JsonLdBuilder — ساختار دادهٔ schema.org/NewsArticle (DOC-092 §7).
 */

namespace Modules\News\Domain\Services;

use Modules\CoreUser\Contracts\UserSnapshot;
use Modules\News\Domain\Entities\Article;

/**
 * JsonLdBuilder — دامنهٔ خالص (بدون I/O) — خروجی با @json در ویو درج می‌شود.
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class JsonLdBuilder
{
    /**
     * @return array<string, mixed> آرایهٔ JSON-LD (NewsArticle)
     */
    public function forArticle(Article $article, ?UserSnapshot $author = null, string $canonicalUrl = '', ?string $imageUrl = null): array
    {
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $article->title(),
            'inLanguage' => 'fa',
            'articleSection' => $article->categoryId() !== null ? (string) $article->categoryId() : '',
        ];

        if ($article->lead() !== null && $article->lead() !== '') {
            $jsonLd['description'] = $article->lead();
        }
        if ($article->publishedAt() !== null) {
            $jsonLd['datePublished'] = $article->publishedAt()->format(\DateTimeInterface::ATOM);
        }
        if ($article->updatedAt() !== null) {
            $jsonLd['dateModified'] = $article->updatedAt()->format(\DateTimeInterface::ATOM);
        }
        if ($author !== null) {
            $jsonLd['author'] = ['@type' => 'Person', 'name' => $author->displayName()];
        }
        if ($canonicalUrl !== '') {
            $jsonLd['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => $canonicalUrl];
        }
        if ($imageUrl !== null && $imageUrl !== '') {
            $jsonLd['image'] = $imageUrl;
        }

        return \array_filter($jsonLd, static fn (mixed $v): bool => $v !== '' && $v !== null);
    }
}

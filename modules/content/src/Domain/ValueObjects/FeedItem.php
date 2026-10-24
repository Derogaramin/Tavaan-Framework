<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * FeedItem — VO یک قلم فید (RSS/Atom).
 */

namespace Modules\Content\Domain\ValueObjects;

/**
 * @api 1.0
 * @see modules/content/README.md
 */
final class FeedItem
{
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $guid,
        public readonly string $summary,
        public readonly \DateTimeImmutable $publishedAt,
        public readonly string $authorName = '',
        public readonly string $authorEmail = '',
    ) {
    }
}

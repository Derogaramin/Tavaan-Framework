<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * FeedMeta — VO متادیتای کانال فید.
 */

namespace Modules\Content\Domain\ValueObjects;

/**
 * @api 1.0
 * @see modules/content/README.md
 */
final class FeedMeta
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $siteUrl,
        public readonly string $feedUrl,
        public readonly string $language = 'fa',
    ) {
    }
}

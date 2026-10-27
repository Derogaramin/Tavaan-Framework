<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * PageWasPublished — رویداد انتشار صفحه.
 */

namespace Modules\Content\Domain\Events;

/**
 * رویداد `content.page.published` — پس از انتشار صفحه.
 *
 * @api 1.0
 */
final readonly class PageWasPublished
{
    public function __construct(
        public string $pageId,
        public string $slug,
        public string $title,
        public ?\DateTimeImmutable $publishedAt = null,
    ) {
    }

    public function eventName(): string
    {
        return 'content.page.published';
    }
}

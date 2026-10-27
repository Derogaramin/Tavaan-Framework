<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * PageWasUpdated — رویداد ویرایش صفحه.
 */

namespace Modules\Content\Domain\Events;

/**
 * رویداد `content.page.updated` — پس از ویرایش محتوا.
 *
 * @api 1.0
 */
final readonly class PageWasUpdated
{
    public function __construct(
        public string $pageId,
        public string $slug,
        public string $title,
    ) {
    }

    public function eventName(): string
    {
        return 'content.page.updated';
    }
}

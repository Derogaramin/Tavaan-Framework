<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqItemWasUpdated — رویداد ویرایش یک پرسش.
 */

namespace Modules\Faq\Domain\Events;

/**
 * رویداد `faq.item.updated` — پس از ویرایش پرسش/پاسخ/دسته.
 *
 * @api 1.0
 */
final readonly class FaqItemWasUpdated
{
    public function __construct(
        public string $itemId,
        public string $question,
    ) {
    }

    public function eventName(): string
    {
        return 'faq.item.updated';
    }
}

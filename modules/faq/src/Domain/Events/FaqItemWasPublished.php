<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqItemWasPublished — رویداد انتشار یک پرسش.
 */

namespace Modules\Faq\Domain\Events;

/**
 * رویداد `faq.item.published` — پس از انتشار پرسش در نمای عمومی.
 *
 * @api 1.0
 */
final readonly class FaqItemWasPublished
{
    public function __construct(
        public string $itemId,
        public string $question,
    ) {
    }

    public function eventName(): string
    {
        return 'faq.item.published';
    }
}

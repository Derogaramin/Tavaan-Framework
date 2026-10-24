<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * ExcerptGenerator — خلاصهٔ متن از HTML (mb-امن، فارسی‌اول).
 */

namespace Modules\Content\Domain\Services;

/**
 * ExcerptGenerator — strip tags + decode entities + شکستن در مرز کلمه + «…».
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class ExcerptGenerator
{
    public function __construct(private readonly int $defaultChars = 200)
    {
    }

    /** تولید خلاصه از HTML (یا متن ساده). */
    public function fromHtml(string $html, ?int $chars = null): string
    {
        $chars = $chars ?? $this->defaultChars;

        $text = \strip_tags($html);
        $text = \html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) \preg_replace('/\s+/u', ' ', $text);
        $text = \trim($text);

        if (\mb_strlen($text) <= $chars) {
            return $text;
        }

        $cut = \mb_substr($text, 0, $chars);
        $lastSpace = \mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > $chars * 0.6) {
            $cut = \mb_substr($cut, 0, $lastSpace);
        }

        return \rtrim($cut, ' .،,;:ـ') . '…';
    }
}

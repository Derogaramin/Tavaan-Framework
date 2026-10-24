<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * QuoteBuilder — ساخت نقل‌قول markdown از یک پست (DOC-094 §6).
 */

namespace Modules\Forum\Domain\Services;

/**
 * QuoteBuilder — دامنهٔ خالص: html → متن ساده → blockquote با ذکر نویسنده.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class QuoteBuilder
{
    public function __construct(private readonly int $maxLength = 800)
    {
    }

    /**
     * @param string $bodyHtml بدنهٔ رندرشدهٔ پست مبدأ (HTML سانیتیزه‌شده)
     *
     * @return string markdown آمادهٔ prefill در فرم پاسخ
     */
    public function build(string $bodyHtml, string $authorName): string
    {
        $text = \trim((string) \preg_replace('/\s+/u', ' ', \strip_tags($bodyHtml)));
        if ($text !== '') {
            $text .= ' …';
        }
        $text = \mb_substr($text, 0, \max(50, $this->maxLength));
        $text = \rtrim($text, ' …');

        $lines = [];
        foreach (\explode("\n", $text) as $line) {
            $lines[] = '> ' . $line;
        }
        $quote = $lines === [] ? '' : \implode("\n", $lines);

        $author = \trim($authorName);
        $header = $author !== '' ? '> **' . $author . '** نوشت:' : '> نقل‌قول:';

        return $header . "\n" . $quote . "\n\n";
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * SpamFilter — heuristic ضداسپم پست‌ها (DOC-094 §7).
 */

namespace Modules\Forum\Domain\Services;

/**
 * SpamFilter — دامنهٔ خالص (بدون I/O)، هم‌راستا با CommentModerator ماژول
 * comment: لینک زیاد یا واژهٔ مسدود → اسپم.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class SpamFilter
{
    /**
     * @param array{max_links?: int, blocked_words?: list<string>} $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    /** آیا بدنه (raw markdown/html) اسپم است؟ */
    public function isSpam(string $body): bool
    {
        $maxLinks = \max(0, (int) ($this->config['max_links'] ?? 3));
        if ($this->linkCount($body) > $maxLinks) {
            return true;
        }

        $lower = \mb_strtolower($body);
        foreach ((array) ($this->config['blocked_words'] ?? []) as $word) {
            $word = \mb_strtolower(\trim((string) $word));
            if ($word !== '' && \str_contains($lower, $word)) {
                return true;
            }
        }

        return false;
    }

    /** شمارش لینک‌ها — markdown `[..](url)` و html `<a href>`. */
    public function linkCount(string $body): int
    {
        $markdown = \preg_match_all('/\]\s*\(\s*https?:\/\//i', $body);
        $html = \preg_match_all('/<a\s[^>]*href\s*=/i', $body);

        return (int) $markdown + (int) $html;
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * CommentModerator — سیاست moderation (DOC-091 §5: صف بررسی + اسپم).
 */

namespace Modules\Comment\Domain\Services;

use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\Enums\CommentStatus;

/**
 * CommentModerator — تصمیم‌گیر وضعیت نظر جدید (دامنهٔ خالص، بدون I/O).
 *
 * قواعد (به ترتیب):
 * 1. اسپم: لینک بیش از `max_links` یا واژهٔ مسدود → `spam`
 * 2. `mode=approve_all` → `approved`
 * 3. `mode=manual` + سابقهٔ تأیید نویسنده + `auto_approve_trusted` → `approved`
 * 4. در غیر این صورت → `pending`
 *
 * @api 1.0
 * @see modules/comment/README.md
 */
final class CommentModerator
{
    /**
     * @param array{mode?: string, auto_approve_trusted?: bool, max_links?: int, blocked_words?: list<string>} $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    /** تعیین وضعیت نظر تازه‌ارسال‌شده. */
    public function decide(Comment $comment, bool $authorHasApprovedHistory = false): CommentStatus
    {
        if ($this->isSpam($comment)) {
            return CommentStatus::Spam;
        }

        $mode = (string) ($this->config['mode'] ?? 'manual');
        if ($mode === 'approve_all') {
            return CommentStatus::Approved;
        }

        if ($mode === 'manual'
            && $authorHasApprovedHistory
            && (bool) ($this->config['auto_approve_trusted'] ?? false)) {
            return CommentStatus::Approved;
        }

        return CommentStatus::Pending;
    }

    /** بررسی heuristic اسپم (لینک زیاد / واژهٔ مسدود). */
    public function isSpam(Comment $comment): bool
    {
        $maxLinks = (int) ($this->config['max_links'] ?? 3);
        if ($comment->linkCount() > $maxLinks) {
            return true;
        }

        $body = \mb_strtolower($comment->body);
        foreach ((array) ($this->config['blocked_words'] ?? []) as $word) {
            $word = \mb_strtolower(\trim((string) $word));
            if ($word !== '' && \str_contains($body, $word)) {
                return true;
            }
        }

        return false;
    }
}

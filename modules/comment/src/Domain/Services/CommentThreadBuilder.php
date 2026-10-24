<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * CommentThreadBuilder — ساخت رشتهٔ تو‌در‌تو از فهرست مسطح (DOC-091 §6: nested).
 */

namespace Modules\Comment\Domain\Services;

use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\ValueObjects\ThreadNode;

/**
 * CommentThreadBuilder — الگوریتم O(n): نگاشت id → گره، سپس اتصال فرزندان.
 *
 * والدِ غایب (حذف‌شده/نامعتبر) → گره به ریشه منتقل می‌شود (بدون از‌دست‌رفتن نظر).
 * ترتیب: حفظ ترتیب ورودی در هر سطح (معمولاً زمانی صعودی).
 *
 * @api 1.0
 * @see modules/comment/README.md
 */
final class CommentThreadBuilder
{
    public function __construct(private readonly int $maxDepth = 8)
    {
    }

    /**
     * @param list<Comment> $comments فهرست مسطح (ترتیب زمانی صعودی)
     *
     * @return list<ThreadNode> جنگل ریشه‌ها
     */
    public function build(array $comments): array
    {
        $nodes = [];
        foreach ($comments as $comment) {
            $nodes[$comment->id] = new ThreadNode($comment);
        }

        $roots = [];
        foreach ($comments as $comment) {
            $node = $nodes[$comment->id];
            $parent = $comment->parentId !== null && $comment->parentId !== ''
                ? ($nodes[$comment->parentId] ?? null)
                : null;

            if ($parent === null || $parent === $node) {
                $roots[] = $node;
                continue;
            }

            // محدودیت عمق: فراتر از maxDepth → هم‌سطح ریشه (زیر ریشهٔ خودش نه)
            if ($this->depthOf($nodes, $parent) >= $this->maxDepth) {
                $roots[] = $node;
                continue;
            }

            $parent->children[] = $node;
        }

        return $roots;
    }

    /**
     * @param array<string, ThreadNode> $nodes
     */
    private function depthOf(array $nodes, ThreadNode $node): int
    {
        $depth = 0;
        $current = $node->comment;
        $guard = 0;
        while ($current->parentId !== null && isset($nodes[$current->parentId]) && $guard < $this->maxDepth + 2) {
            $depth++;
            $current = $nodes[$current->parentId]->comment;
            $guard++;
        }

        return $depth;
    }
}

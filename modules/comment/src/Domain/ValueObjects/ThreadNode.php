<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * ThreadNode — گره درخت رشتهٔ نظرات.
 */

namespace Modules\Comment\Domain\ValueObjects;

use Modules\Comment\Domain\Entities\Comment;

/**
 * @api 1.0
 * @see modules/comment/README.md
 */
final class ThreadNode
{
    /** @var list<ThreadNode> */
    public array $children = [];

    public function __construct(public readonly Comment $comment)
    {
    }

    public function depth(): int
    {
        $max = 0;
        foreach ($this->children as $child) {
            $max = \max($max, 1 + $child->depth());
        }

        return $max;
    }

    /** شمارش کل زیردرخت (شامل خود گره). */
    public function count(): int
    {
        $total = 1;
        foreach ($this->children as $child) {
            $total += $child->count();
        }

        return $total;
    }
}

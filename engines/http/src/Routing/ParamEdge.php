<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

/**
 * تَوان | Tavaan — Routing
 * ParamEdge — یال پارامتریک گرهٔ Trie (نمایش فشردهٔ حافظه).
 *
 * به‌جای آرایهٔ ۶‌کلیدی (~۵۶۰ بایت) یک آبجکت ~۵۶ بایتی (DOC-040 §12).
 *
 * @api 1.0 (internal)
 * @see docs/03-engines/router.md
 */
final class ParamEdge
{
    public function __construct(
        public readonly string $name,
        public readonly string $regex,
        public readonly TrieNode $node,
        public readonly int $specificity,
        public readonly int $order,
    ) {
    }
}

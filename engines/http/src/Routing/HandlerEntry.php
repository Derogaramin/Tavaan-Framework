<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

/**
 * تَوان | Tavaan — Routing
 * HandlerEntry — یک handler برگ Trie (method + route) به‌صورت فشرده.
 *
 * فهرست تخت `list<HandlerEntry>` روی گره، جایگزین نگاشت
 * `method → list<array{route,specificity,order}>` می‌شود (هر آرایهٔ hashed
 * در PHP حداقل ~۳۴۴ بایت هزینه دارد؛ DOC-040 §12).
 *
 * @api 1.0 (internal)
 * @see docs/03-engines/router.md
 */
final class HandlerEntry
{
    public function __construct(
        public readonly string $method,
        public readonly Route $route,
        public readonly int $specificity,
        public readonly int $order,
    ) {
    }
}

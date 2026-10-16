<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Attributes;

/**
 * تَوان | Tavaan — Api
 * Api — متادیتای OpenAPI روی متد/کلاس Controller (DOC-044 §4.1).
 *
 * ```php
 * #[Api(tags: ['products'], summary: 'فهرست محصولات')]
 * public function index(): AnonymousResourceCollection { … }
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class Api
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public readonly array $tags = [],
        public readonly string $summary = '',
        public readonly string $description = '',
        public readonly string $operationId = '',
        public readonly bool $deprecated = false,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Attributes;

/**
 * تَوان | Tavaan — Api
 * ApiParam — تعریف پارامتر مسیر/کوئری/هدر (تکرارپذیر).
 *
 * ```php
 * #[ApiParam(name: 'page', in: 'query', type: 'integer', description: 'شمارهٔ صفحه')]
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiParam
{
    /**
     * @param string      $in   path|query|header|cookie
     * @param string      $type string|integer|number|boolean|array
     * @param list<mixed> $enum
     */
    public function __construct(
        public readonly string $name,
        public readonly string $in = 'query',
        public readonly string $type = 'string',
        public readonly bool $required = false,
        public readonly string $description = '',
        public readonly ?string $format = null,
        public readonly array $enum = [],
    ) {
    }
}

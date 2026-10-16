<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Attributes;

/**
 * تَوان | Tavaan — Api
 * ApiResponse — تعریف یک پاسخ OpenAPI (تکرارپذیر — DOC-044 §4.1).
 *
 * ```php
 * #[ApiResponse(status: 200, description: 'موفق', resource: ProductResource::class)]
 * #[ApiResponse(status: 422, description: 'خطای اعتبارسنجی')]
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiResponse
{
    /**
     * @param class-string<\Tavaan\Engine\Api\Resources\JsonResource>|null $resource
     */
    public function __construct(
        public readonly int $status = 200,
        public readonly string $description = '',
        public readonly ?string $resource = null,
        public readonly string $contentType = 'application/json',
    ) {
    }
}

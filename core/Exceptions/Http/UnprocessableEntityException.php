<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions\Http;

/**
 * خطای HTTP با وضعیت 422.
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
final class UnprocessableEntityException extends HttpException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $messageId = 'http.unprocessable_entity',
        array $context = [],
        ?string $hint = null,
        array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(422, $messageId, $context, $hint, null, $headers, 0, $previous);
    }
}

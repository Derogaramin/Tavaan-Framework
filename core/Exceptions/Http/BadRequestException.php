<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions\Http;

/**
 * خطای HTTP با وضعیت 400.
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
final class BadRequestException extends HttpException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $messageId = 'http.bad_request',
        array $context = [],
        ?string $hint = null,
        array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(400, $messageId, $context, $hint, null, $headers, 0, $previous);
    }
}

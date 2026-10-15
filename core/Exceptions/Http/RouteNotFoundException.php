<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions\Http;

/**
 * خطای HTTP 404 — روت/منبع یافت نشد (حوزهٔ کد: HT).
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
final class RouteNotFoundException extends HttpException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $messageId = 'http.route.not_found',
        array $context = [],
        ?string $hint = null,
        array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(404, $messageId, $context, $hint, null, $headers, 0, $previous);
    }

    /**
     * Named Constructor (DOC-013 §3.1).
     */
    public static function forPath(string $method, string $path): self
    {
        return new self(
            messageId: 'http.route.not_found',
            context: ['method' => $method, 'path' => $path],
            hint: 'مسیر را در routes/ بررسی کنید یا `tvn route:list` بزنید.',
        );
    }
}

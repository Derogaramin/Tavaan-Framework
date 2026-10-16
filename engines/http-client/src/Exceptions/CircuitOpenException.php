<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Exceptions;

/**
 * تَوان | Tavaan — HttpClient
 * CircuitOpenException — مدار سرویس باز است (DOC-064 §4).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class CircuitOpenException extends HttpClientException
{
    public function __construct(string $service, int $cooldown = 0)
    {
        parent::__construct(
            'http_client.circuit_open',
            ['service' => $service, 'cooldown' => $cooldown],
            'سرویس به‌دلیل خطاهای پیاپی موقتاً قطع است؛ پس از cooldown نیم‌باز می‌شود.',
        );
    }
}

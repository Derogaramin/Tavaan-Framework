<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Transport;

/**
 * تَوان | Tavaan — HttpClient
 * TransportResponse — پاسخ خام لایهٔ transport (DOC-064 §3.1).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class TransportResponse
{
    /**
     * @param array<string, string> $headers کلیدها lowercase
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly string $effectiveUrl = '',
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[\strtolower($name)] ?? null;
    }

    public function isRedirect(): bool
    {
        return \in_array($this->status, [301, 302, 303, 307, 308], true);
    }
}

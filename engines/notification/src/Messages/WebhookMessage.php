<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Messages;

/**
 * تَوان | Tavaan — Notification
 * WebhookMessage — پیام کانال webhook (HTTP POST؛ DOC-066 §2).
 *
 * 🔒 ارسال با موتور HttpClient — SSRF Guard فعال.
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class WebhookMessage
{
    /** @var array<string, string> */
    private array $headers = [];

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        public readonly string $url,
        public readonly array $payload,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function create(string $url, array $payload = []): self
    {
        return new self($url, $payload);
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}

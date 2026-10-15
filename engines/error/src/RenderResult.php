<?php

declare(strict_types=1);

namespace Tavaan\Engine\Error;

/**
 * تَوان | Tavaan — Error
 * RenderResult — خروجی رندر خطا (تا M2 که Response ساخته شود).
 *
 * @api 1.0 (internal)
 * @see docs/03-engines/error.md
 */
final readonly class RenderResult
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public string $body,
        public string $contentType = 'text/html; charset=utf-8',
        public array $headers = [],
    ) {
    }

    /** ارسال خروجی (CLI: stdout؛ HTTP: header + body). */
    public function send(): void
    {
        if (PHP_SAPI !== 'cli' && !\headers_sent()) {
            \http_response_code($this->status);
            \header('Content-Type: ' . $this->contentType);
            \header('X-Tavaan-Ref: ' . ($this->headers['X-Tavaan-Ref'] ?? ''));
            foreach ($this->headers as $name => $value) {
                \header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }
}

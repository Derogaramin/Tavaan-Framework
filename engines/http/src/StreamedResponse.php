<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http;

/**
 * تَوان | Tavaan — Http
 * StreamedResponse — پاسخ جریان‌دار (chunked) بدون مصرف حافظه.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class StreamedResponse extends Response
{
    /** @var \Closure(): void */
    private \Closure $callback;

    private bool $sent = false;

    /**
     * @param \Closure(): void      $callback خروجی را echo می‌کند
     * @param array<string, string> $headers
     */
    public function __construct(\Closure $callback, int $status = 200, array $headers = [])
    {
        parent::__construct('', $status, $headers);
        $this->callback = $callback;
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', 'application/octet-stream');
        }
    }

    public function body(): string
    {
        if (!$this->sent) {
            \ob_start();
            ($this->callback)();
            $this->body = (string) \ob_get_clean();
            $this->sent = true;
        }

        return $this->body;
    }

    public function send(): void
    {
        $this->sendHeaders();
        if (!$this->sent) {
            ($this->callback)();
            $this->sent = true;
            return;
        }
        echo $this->body;
    }
}

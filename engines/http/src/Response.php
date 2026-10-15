<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http;

use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Cookie\Cookie;

/**
 * تَوان | Tavaan — Http
 * Response — پاسخ HTTP تغییرناپذیر (Immutable).
 *
 * ⚠️ `send()` بدون exit است؛ ترتیب صحیح در Kernel::sendAndTerminate
 * (send → terminate → پایان اسکریپت) رعایت می‌شود (DOC-039 §4.2).
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
class Response implements ResponseInterface
{
    /** @var array<int, string> */
    public const REASONS = [
        200 => 'OK', 201 => 'Created', 204 => 'No Content', 301 => 'Moved Permanently',
        302 => 'Found', 304 => 'Not Modified', 400 => 'Bad Request', 401 => 'Unauthorized',
        403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed',
        413 => 'Payload Too Large', 419 => 'Page Expired', 422 => 'Unprocessable Entity',
        429 => 'Too Many Requests', 500 => 'Internal Server Error', 503 => 'Service Unavailable',
    ];

    protected HeaderBag $headers;

    /** @var list<Cookie> */
    protected array $cookies = [];

    protected string $reason = '';

    public function __construct(
        protected string $body = '',
        protected int $status = 200,
        array $headers = [],
    ) {
        $this->headers = new HeaderBag($headers);
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', 'text/html; charset=UTF-8');
        }
    }

    /** ساخت پاسخ JSON. @param int $flags */
    public static function json(mixed $data, int $status = 200, int $flags = 0, array $headers = []): static
    {
        $flags |= JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $body = (string) \json_encode($data, $flags | JSON_THROW_ON_ERROR);

        $response = new static($body, $status, ['Content-Type' => 'application/json; charset=UTF-8', ...$headers]);

        return $response;
    }

    // ─── Immutable API ────────────────────────────────────────────────────

    public function statusCode(): int
    {
        return $this->status;
    }

    public function reasonPhrase(): string
    {
        return $this->reason !== '' ? $this->reason : (self::REASONS[$this->status] ?? '');
    }

    public function withStatus(int $code, string $reason = ''): static
    {
        $clone = clone $this;
        $clone->status = $code;
        $clone->reason = $reason;

        return $clone;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers->get($key, $default);
    }

    public function hasHeader(string $key): bool
    {
        return $this->headers->has($key);
    }

    /** @return array<string, list<string>> */
    public function headers(): array
    {
        return $this->headers->allPreserveCase();
    }

    public function withHeader(string $key, string $value, bool $replace = true): static
    {
        $clone = clone $this;
        $clone->headers = clone $this->headers;
        if ($replace) {
            $clone->headers->set($key, $value);
        } else {
            $clone->headers->add($key, $value);
        }

        return $clone;
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): static
    {
        $response = $this;
        foreach ($headers as $key => $value) {
            $response = $response->withHeader((string) $key, $value);
        }

        return $response;
    }

    public function withoutHeader(string $key): static
    {
        $clone = clone $this;
        $clone->headers = clone $this->headers;
        $clone->headers->remove($key);

        return $clone;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function hasBody(): bool
    {
        return $this->body !== '';
    }

    public function withBody(string $body): static
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    public function withJson(mixed $data, int $status = 200, int $flags = 0): static
    {
        $flags |= JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        return $this
            ->withBody((string) \json_encode($data, $flags | JSON_THROW_ON_ERROR))
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withStatus($status);
    }

    public function cookie(Cookie $cookie): static
    {
        $clone = clone $this;
        $clone->cookies = [...$this->cookies, $cookie];

        return $clone;
    }

    /** @return list<Cookie> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function isRedirect(): bool
    {
        return \in_array($this->status, [301, 302, 303, 307, 308], true);
    }

    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    public function isServerError(): bool
    {
        return $this->status >= 500;
    }

    public function withProtocolVersion(string $version): static
    {
        return $this;   // نسخهٔ پروتکل در send ثابت است (1.1)
    }

    public function __clone(): void
    {
        // HeaderBag در withXها clone می‌شود؛ اینجا برای اطمینان
    }

    // ─── ارسال ────────────────────────────────────────────────────────────

    /**
     * ارسال headers + cookies + body — بدون exit (Kernel terminate را صدا می‌زند).
     */
    public function send(): void
    {
        $this->sendHeaders();
        $this->sendContent();
    }

    protected function sendHeaders(): void
    {
        if (PHP_SAPI === 'cli' || \headers_sent()) {
            return;
        }

        // 🔒 Information Leak: هدرهای SAPI حذف شوند
        \header_remove('X-Powered-By');

        \http_response_code($this->status);

        foreach ($this->headers->allPreserveCase() as $name => $values) {
            $replace = true;
            foreach ($values as $value) {
                \header($name . ': ' . $value, $replace, $this->status);
                $replace = false;
            }
        }

        foreach ($this->cookies as $cookie) {
            \setcookie($cookie->name(), $cookie->value(), $cookie->toArray());
        }
    }

    protected function sendContent(): void
    {
        echo $this->body;
    }
}

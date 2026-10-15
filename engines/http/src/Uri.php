<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http;

use Tavaan\Core\Contracts\Http\UriInterface;

/**
 * تَوان | Tavaan — Http
 * Uri — پارس/ساخت URI مطابق RFC 3986 (immutable).
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class Uri implements UriInterface
{
    private string $scheme = 'http';

    private string $host = '';

    private ?int $port = null;

    private string $path = '/';

    private string $query = '';

    private string $fragment = '';

    private string $user = '';

    private string $pass = '';

    public function __construct(string $uri = '')
    {
        if ($uri === '') {
            return;
        }

        $parts = \parse_url($uri);
        if ($parts === false) {
            throw new \InvalidArgumentException(\sprintf('Malformed URI: [%s]', \substr($uri, 0, 120)));
        }

        $this->scheme = \strtolower($parts['scheme'] ?? 'http');
        $this->host = \strtolower($parts['host'] ?? '');
        $this->port = $parts['port'] ?? null;
        $this->path = $parts['path'] ?? '/';
        $this->query = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? '';
        $this->user = $parts['user'] ?? '';
        $this->pass = $parts['pass'] ?? '';

        if ($this->path === '') {
            $this->path = '/';
        }
    }

    public static function fromParts(string $scheme, string $host, ?int $port, string $path, string $query = ''): self
    {
        $uri = $scheme . '://' . $host;
        if ($port !== null) {
            $uri .= ':' . $port;
        }

        return new self($uri . ($path === '' ? '/' : $path) . ($query === '' ? '' : '?' . $query));
    }

    public function scheme(): string
    {
        return $this->scheme;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function port(): ?int
    {
        return $this->port;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(): string
    {
        return $this->query;
    }

    public function fragment(): string
    {
        return $this->fragment;
    }

    public function userInfo(): string
    {
        return $this->user === '' ? '' : $this->user . ($this->pass === '' ? '' : ':' . $this->pass);
    }

    public function withQuery(array|string $query): static
    {
        $clone = clone $this;
        $clone->query = \is_array($query) ? \http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $query;

        return $clone;
    }

    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->path = $path === '' ? '/' : $path;

        return $clone;
    }

    public function withHost(string $host): static
    {
        $clone = clone $this;
        $clone->host = \strtolower($host);

        return $clone;
    }

    public function withScheme(string $scheme): static
    {
        $clone = clone $this;
        $clone->scheme = \strtolower($scheme);

        return $clone;
    }

    /** URL کامل بدون fragment. */
    public function url(): string
    {
        return (string) $this;
    }

    public function __toString(): string
    {
        $authority = $this->host;
        if ($authority === '') {
            return $this->path . ($this->query === '' ? '' : '?' . $this->query);
        }

        $userInfo = $this->userInfo();
        $port = $this->port === null || ($this->scheme === 'http' && $this->port === 80) || ($this->scheme === 'https' && $this->port === 443)
            ? ''
            : ':' . $this->port;

        return $this->scheme . '://'
            . ($userInfo === '' ? '' : $userInfo . '@')
            . $authority . $port
            . $this->path
            . ($this->query === '' ? '' : '?' . $this->query)
            . ($this->fragment === '' ? '' : '#' . $this->fragment);
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http;

/**
 * تَوان | Tavaan — Http
 * ResponsePreparer — ETag، Conditional (304)، Compression و Content-Length.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class ResponsePreparer
{
    /** @var list<string> content-typeهای فشرده‌شدنی */
    private const COMPRESSIBLE = [
        'text/html', 'text/plain', 'text/css', 'text/javascript',
        'application/javascript', 'application/json', 'application/xml',
        'text/xml', 'image/svg+xml', 'application/rss+xml', 'application/manifest+json',
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function prepare(Response $response, Request $request): Response
    {
        if ($response->isRedirect() || $response->statusCode() === 204 || $response instanceof StreamedResponse) {
            return $response;
        }

        // ETag
        $etagEnabled = (bool) ($this->config['etag']['enabled'] ?? true);
        if ($etagEnabled && $response->hasBody() && !$response->hasHeader('ETag')) {
            $algo = (string) ($this->config['etag']['algorithm'] ?? 'xxh128');
            $algo = \in_array($algo, \hash_algos(), true) ? $algo : 'sha256';
            $etag = '"' . \hash($algo, $response->body()) . '"';
            $response = $response->withHeader('ETag', $etag);
        }

        // Conditional → 304
        if ($this->isFresh($request, $response)) {
            return $response->withStatus(304)->withBody('')->withoutHeader('Content-Length')->withoutHeader('Content-Type');
        }

        // Compression
        if ($this->shouldCompress($response, $request)) {
            $response = $this->compress($response, $request);
        }

        // Content-Length
        if (!$response->hasHeader('Content-Length') && $response->hasBody()) {
            $response = $response->withHeader('Content-Length', (string) \strlen($response->body()));
        }

        return $response;
    }

    private function isFresh(Request $request, Response $response): bool
    {
        if (!\in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch !== null && $response->hasHeader('ETag')) {
            $etags = \array_map(\trim(...), \explode(',', $ifNoneMatch));
            $current = (string) $response->header('ETag');
            if (\in_array($current, $etags, true) || \in_array('*', $etags, true)) {
                return true;
            }
        }

        $ifModified = $request->header('If-Modified-Since');
        if ($ifModified !== null && $response->hasHeader('Last-Modified')) {
            $modified = \strtotime((string) $response->header('Last-Modified')) ?: 0;
            $since = \strtotime($ifModified) ?: 0;

            return $modified <= $since;
        }

        return false;
    }

    private function shouldCompress(Response $response, Request $request): bool
    {
        if (!(bool) ($this->config['compression']['enabled'] ?? true)) {
            return false;
        }
        $size = \strlen($response->body());
        $minSize = (int) ($this->config['compression']['min_size'] ?? 1024);
        if ($size < $minSize) {
            return false;
        }
        if ($response->hasHeader('Content-Encoding')) {
            return false;
        }
        $type = \strtolower((string) ($response->header('Content-Type') ?? ''));
        $mime = \trim(\explode(';', $type)[0]);
        if (!\in_array($mime, self::COMPRESSIBLE, true)) {
            return false;
        }

        return $this->acceptedEncoding($request) !== null;
    }

    private function acceptedEncoding(Request $request): ?string
    {
        $header = (string) ($request->header('Accept-Encoding') ?? '');
        $allowed = (array) ($this->config['compression']['algorithms'] ?? ['gzip', 'deflate']);

        foreach (['br', 'gzip', 'deflate'] as $enc) {
            if (!\in_array($enc, $allowed, true)) {
                continue;
            }
            if (\str_contains($header, $enc)) {
                if ($enc === 'br' && !\function_exists('brotli_compress')) {
                    continue;
                }

                return $enc;
            }
        }

        return null;
    }

    private function compress(Response $response, Request $request): Response
    {
        $enc = $this->acceptedEncoding($request);
        if ($enc === null) {
            return $response;
        }

        $level = (int) ($this->config['compression']['level'] ?? 5);
        $body = $response->body();

        $compressed = match ($enc) {
            'br'      => \function_exists('brotli_compress') ? \brotli_compress($body, 4) : false,
            'gzip'    => \function_exists('gzencode') ? \gzencode($body, $level) : false,
            'deflate' => \function_exists('gzdeflate') ? \gzdeflate($body, $level) : false,
            default   => false,
        };

        if ($compressed === false || \strlen($compressed) >= \strlen($body)) {
            return $response;
        }

        return $response->withBody($compressed)
            ->withHeader('Content-Encoding', $enc)
            ->withHeader('Vary', 'Accept-Encoding', false)
            ->withHeader('Content-Length', (string) \strlen($compressed));
    }
}

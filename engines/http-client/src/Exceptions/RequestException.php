<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Exceptions;

use Tavaan\Engine\HttpClient\Response;

/**
 * تَوان | Tavaan — HttpClient
 * RequestException — پاسخ 4xx/5xx وقتی `throw()` فراخوانی شود (DOC-064 §2).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class RequestException extends HttpClientException
{
    public function __construct(public readonly Response $response, string $url = '')
    {
        parent::__construct(
            'http_client.request_failed',
            ['url' => $url !== '' ? $url : $response->effectiveUrl(), 'status' => $response->status()],
            \mb_substr($response->body(), 0, 200),
        );
    }

    public static function for(Response $response, string $url = ''): self
    {
        return new self($response, $url);
    }
}

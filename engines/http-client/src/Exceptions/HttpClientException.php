<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — HttpClient
 * HttpClientException — پایهٔ خطاهای کلاینت HTTP (DOC-064).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
class HttpClientException extends TavaanException
{
    public static function connectionFailed(string $url, string $reason): static
    {
        return new static('http_client.connection_failed', ['url' => $url, 'reason' => $reason]);
    }

    public static function tooManyRedirects(string $url): static
    {
        return new static('http_client.too_many_redirects', ['url' => $url], 'حداکثر redirectها (پیش‌فرض ۵) رد شد.');
    }

    public static function responseTooLarge(string $url, int $maxBytes): static
    {
        return new static('http_client.response_too_large', ['url' => $url, 'max' => $maxBytes]);
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Exceptions;

/**
 * تَوان | Tavaan — HttpClient
 * ConnectionException — خطای اتصال/timeout (قابل retry؛ DOC-064 §5).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class ConnectionException extends HttpClientException
{
}

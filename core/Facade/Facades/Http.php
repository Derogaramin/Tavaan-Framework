<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade کلاینت HTTP (Http).
 *
 * @method static \Tavaan\Engine\HttpClient\Response get(string $url, array|string|null $query = null)
 * @method static \Tavaan\Engine\HttpClient\Response post(string $url, mixed $data = null)
 * @method static \Tavaan\Engine\HttpClient\Response put(string $url, mixed $data = null)
 * @method static \Tavaan\Engine\HttpClient\Response patch(string $url, mixed $data = null)
 * @method static \Tavaan\Engine\HttpClient\Response delete(string $url, mixed $data = null)
 * @method static \Tavaan\Engine\HttpClient\Response head(string $url)
 * @method static \Tavaan\Engine\HttpClient\Response send(string $method, string $url, mixed $data = null, array $options = [])
 * @method static \Tavaan\Engine\HttpClient\PendingRequest withHeaders(array $headers)
 * @method static \Tavaan\Engine\HttpClient\PendingRequest withHeader(string $name, string $value)
 * @method static \Tavaan\Engine\HttpClient\PendingRequest withToken(string $token, string $type = 'Bearer')
 * @method static \Tavaan\Engine\HttpClient\PendingRequest withCookies(array $cookies, string $domain = '')
 * @method static \Tavaan\Engine\HttpClient\PendingRequest withQueryParameters(array $query)
 * @method static \Tavaan\Engine\HttpClient\PendingRequest asJson()
 * @method static \Tavaan\Engine\HttpClient\PendingRequest asForm()
 * @method static \Tavaan\Engine\HttpClient\PendingRequest acceptJson()
 * @method static \Tavaan\Engine\HttpClient\PendingRequest timeout(int $seconds)
 * @method static \Tavaan\Engine\HttpClient\PendingRequest retry(int $times, int $sleepMs = 100, ?callable $when = null)
 * @method static \Tavaan\Engine\HttpClient\PendingRequest withoutVerifying()
 * @method static \Tavaan\Engine\HttpClient\PendingRequest withOptions(array $options)
 * @method static \Tavaan\Engine\HttpClient\PendingRequest attach(string $name, mixed $contents, ?string $filename = null, array $headers = [])
 * @method static \Tavaan\Engine\HttpClient\PendingRequest newRequest()
 * @method static array pool(callable $callback)
 * @method static \Tavaan\Engine\HttpClient\Factory fake(array|\Tavaan\Engine\HttpClient\Response|\Closure|null $stubs = null)
 * @method static \Tavaan\Engine\HttpClient\Factory real()
 * @method static \Tavaan\Engine\HttpClient\Response response(string|array $body = '', int $status = 200, array $headers = [])
 * @method static void assertSent(callable $callback)
 * @method static void assertNotSent(callable $callback)
 * @method static void assertSentCount(int $count)
 * @method static list<array{request: \Tavaan\Engine\HttpClient\Request, response: \Tavaan\Engine\HttpClient\Response}> recorded()
 * @mixin \Tavaan\Engine\HttpClient\Factory
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class Http extends Facade
{
    protected static function accessor(): string
    {
        return 'http.client';
    }
}

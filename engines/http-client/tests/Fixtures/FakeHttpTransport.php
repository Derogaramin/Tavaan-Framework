<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Tests\Fixtures;

use Tavaan\Engine\HttpClient\Exceptions\ConnectionException;
use Tavaan\Engine\HttpClient\Exceptions\HttpClientException;
use Tavaan\Engine\HttpClient\Transport\HttpTransportInterface;
use Tavaan\Engine\HttpClient\Transport\TransportResponse;

/**
 * Fixture: transport ساختگی برای تست بدون شبکه.
 *
 * - صفی از پاسخ‌ها (به‌ترتیب مصرف می‌شوند؛ آخرین تکرار می‌شود)
 * - یا callback برای تولید پاسخ از روی درخواست
 * - قابلیت تزریق خطای ConnectionException (برای تست retry)
 * - ضبط همهٔ درخواست‌ها برای assert
 */
final class FakeHttpTransport implements HttpTransportInterface
{
    /** @var list<TransportResponse|\Throwable|\Closure> */
    private array $script = [];

    private int $cursor = 0;

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string, options: array<string, mixed>}> */
    public array $sent = [];

    /**
     * @param list<TransportResponse|\Throwable|\Closure(string, string, array, ?string): TransportResponse> $script
     */
    public function __construct(array $script = [])
    {
        $this->script = $script;
    }

    public static function response(int $status = 200, string $body = '', array $headers = []): TransportResponse
    {
        return new TransportResponse($status, \array_change_key_case($headers), $body);
    }

    /** @param array<string, string> $headers @param array<string, mixed> $options */
    public function send(string $method, string $url, array $headers = [], ?string $body = null, array $options = []): TransportResponse
    {
        $this->sent[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body, 'options' => $options];

        if ($this->script === []) {
            return new TransportResponse(200, [], '');
        }

        $entry = $this->script[\min($this->cursor, \count($this->script) - 1)];
        $this->cursor++;

        if ($entry instanceof \Throwable) {
            throw $entry;
        }

        if ($entry instanceof \Closure) {
            return $entry($method, $url, $headers, $body);
        }

        return $entry;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function callCount(): int
    {
        return \count($this->sent);
    }
}

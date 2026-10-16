<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient;

use Tavaan\Engine\HttpClient\Exceptions\ConnectionException;
use Tavaan\Engine\HttpClient\Transport\CurlTransport;

/**
 * تَوان | Tavaan — HttpClient
 * Pool — اجرای گروهی درخواست‌ها (DOC-064 §2).
 *
 * ```php
 * $responses = Http::pool(fn (Pool $pool) => [
 *     $pool->as('user')->get($url1),
 *     $pool->as('posts')->get($url2),
 * ]);
 * $responses['user']->json();
 * ```
 *
 * 🔒 با curl → موازی (curl_multi)؛ بدون curl → ترتیبی (fallback ایمن).
 * خطای اتصال به‌جای شکستن گروه، در نگاشت نتیجه می‌نشیند.
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class Pool
{
    /** @var list<array{name: string, request: PendingRequest, method: string, url: string, data: mixed}> */
    private array $queue = [];

    /** @var array<string, Response|ConnectionException> */
    private array $results = [];

    public function __construct(private readonly Factory $factory)
    {
    }

    /** نام‌گذاری ورودی — `$pool->as('user')->get(...)`. */
    public function as(string $name): PendingRequest
    {
        return $this->factory->newRequest()->bindPool($this, $name);
    }

    /**
     * ثبت درخواست برای اجرای گروهی (توسط PendingRequest::send).
     *
     * @internal
     */
    public function enqueue(string $name, PendingRequest $request, string $method, string $url, mixed $data): Response
    {
        $this->queue[] = ['name' => $name, 'request' => $request, 'method' => $method, 'url' => $url, 'data' => $data];

        return new Response('', 200);   // placeholder تا dispatch
    }

    /**
     * اجرای همهٔ درخواست‌های ثبت‌شده.
     *
     * @return array<string, Response|ConnectionException>
     */
    public function dispatch(): array
    {
        $transport = $this->factory->transport();

        // 🔒 مسیر موازی فقط با curl و وقتی fake فعال نیست
        if ($transport instanceof CurlTransport && \count($this->queue) > 1 && !$this->factory->isFaking()) {
            $this->dispatchConcurrent($transport);
        } else {
            $this->dispatchSequential();
        }

        return $this->results;
    }

    private function dispatchSequential(): void
    {
        foreach ($this->queue as $job) {
            try {
                $this->results[$job['name']] = $job['request']->sendReal($job['method'], $job['url'], $job['data']);
            } catch (ConnectionException $e) {
                $this->results[$job['name']] = $e;
            }
        }
    }

    /**
     * 🔒 SSRF هر URL پیش از batch و هر redirect حین آن بررسی می‌شود.
     */
    private function dispatchConcurrent(CurlTransport $transport): void
    {
        $jobs = [];
        foreach ($this->queue as $job) {
            try {
                $this->factory->assertSsrfAllowed($job['url']);
                [$url, $headers, $body] = $job['request']->prepareRaw(null, $job['method'], $job['url'], $job['data']);
                $jobs[] = [
                    'key' => $job['name'],
                    'method' => $job['method'],
                    'url' => $url,
                    'headers' => $headers,
                    'body' => $body,
                    'options' => $job['request']->transportOptions(),
                    'ssrf' => fn (string $u): bool => $this->factory->ssrfAllows($u),
                    'request' => $job['request'],
                ];
            } catch (ConnectionException $e) {
                $this->results[$job['name']] = $e;
            } catch (\Tavaan\Core\Exceptions\TavaanException $e) {
                $this->results[$job['name']] = new ConnectionException('http_client.ssrf_blocked', ['url' => $job['url']]);
            }
        }

        $rawResults = $transport->sendConcurrent($jobs);

        foreach ($rawResults as $key => $raw) {
            if ($raw instanceof \Throwable) {
                $this->results[$key] = new ConnectionException('http_client.connection_failed', ['url' => (string) $key, 'reason' => $raw->getMessage()]);
                continue;
            }
            $this->results[$key] = new Response($raw->body, $raw->status, $raw->headers, $raw->effectiveUrl);
        }
    }
}

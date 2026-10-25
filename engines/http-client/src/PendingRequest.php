<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient;

use Tavaan\Engine\HttpClient\Exceptions\HttpClientException;
use Tavaan\Engine\HttpClient\Transport\TransportResponse;

/**
 * تَوان | Tavaan — HttpClient
 * PendingRequest — درخواست fluent با SSRF Guard، Retry، Circuit Breaker
 * و مدیریت redirect با re-check (DOC-064 §2/§3).
 *
 * ```php
 * $response = Http::withHeaders([...])->timeout(10)->retry(3, 100)->get($url);
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class PendingRequest
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<string, string> */
    private array $cookies = [];

    /** @var array<string, mixed> */
    private array $query = [];

    /** '' | json | form */
    private string $bodyType = '';

    /** @var list<array{name: string, contents: mixed, filename: string, headers: array<string, string>}> */
    private array $files = [];

    private int $tries = 1;

    private int $retrySleepMs = 100;

    /** @var \Closure(int, Response|null, ?\Throwable): bool|null */
    private ?\Closure $retryWhen = null;

    private int $maxRedirects = 5;

    /** @var array<string, mixed> */
    private array $options = [];

    private ?string $poolName = null;

    private ?\Tavaan\Engine\HttpClient\Pool $pool = null;

    public function __construct(private readonly Factory $factory)
    {
        $config = $factory->config();

        $this->options = [
            'timeout' => (int) ($config['timeout'] ?? 10),
            'connect_timeout' => (int) ($config['connect_timeout'] ?? 5),
            'verify_tls' => (bool) ($config['verify_tls'] ?? true),
            'proxy' => $config['proxy'] ?? null,
            'ca_bundle' => $config['ca_bundle'] ?? null,
            'max_size' => (int) ($config['max_size'] ?? 0),
        ];

        $userAgent = ((string) ($config['user_agent'] ?? '')) ?: 'Tavaan/1.0 (+https://tavaan.dev)';
        $this->headers = ['User-Agent' => $userAgent, 'Accept' => '*/*'];

        $retry = (array) ($config['retry'] ?? []);
        $this->tries = \max(1, (int) ($retry['times'] ?? 1));
        $this->retrySleepMs = (int) ($retry['sleep'] ?? 100);
    }

    // ─── Fluent ───────────────────────────────────────────────────────────

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        $this->headers = \array_merge($this->headers, $headers);

        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function withToken(string $token, string $type = 'Bearer'): self
    {
        return $this->withHeader('Authorization', \trim($type . ' ' . $token));
    }

    /** @param array<string, string> $cookies */
    public function withCookies(array $cookies, string $domain = ''): self
    {
        $this->cookies = \array_merge($this->cookies, $cookies);

        return $this;
    }

    /** @param array<string, mixed> $query */
    public function withQueryParameters(array $query): self
    {
        $this->query = \array_merge($this->query, $query);

        return $this;
    }

    public function asJson(): self
    {
        $this->bodyType = 'json';
        $this->headers['Content-Type'] = 'application/json';

        return $this;
    }

    public function asForm(): self
    {
        $this->bodyType = 'form';
        $this->headers['Content-Type'] = 'application/x-www-form-urlencoded';

        return $this;
    }

    public function acceptJson(): self
    {
        $this->headers['Accept'] = 'application/json';

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->options['timeout'] = $seconds;

        return $this;
    }

    public function connectTimeout(int $seconds): self
    {
        $this->options['connect_timeout'] = $seconds;

        return $this;
    }

    /**
     * تلاش مجدد — DOC-064 §5.
     *
     * @param int                                    $times  تعداد کل تلاش‌ها
     * @param int                                    $sleepMs پایهٔ backoff (نمایی + jitter)
     * @param callable(int, Response|null, ?\Throwable): bool|null $when شرط سفارشی retry
     */
    public function retry(int $times, int $sleepMs = 100, ?callable $when = null): self
    {
        $this->tries = \max(1, $times);
        $this->retrySleepMs = $sleepMs;
        $this->retryWhen = $when === null ? null : \Closure::fromCallable($when);

        return $this;
    }

    /** 🔴 فقط با آگاهی — TLS verification غیرفعال. */
    public function withoutVerifying(): self
    {
        $this->options['verify_tls'] = false;

        return $this;
    }

    /** @param array<string, mixed> $options */
    public function withOptions(array $options): self
    {
        $this->options = \array_merge($this->options, $options);

        return $this;
    }

    public function maxRedirects(int $max): self
    {
        $this->maxRedirects = \max(0, $max);

        return $this;
    }

    /** پیوست فایل multipart (DOC-064 §2). */
    public function attach(string $name, mixed $contents, ?string $filename = null, array $headers = []): self
    {
        $this->files[] = [
            'name' => $name,
            'contents' => $contents,
            'filename' => $filename ?? ('file-' . \bin2hex(\random_bytes(4))),
            'headers' => $headers,
        ];

        return $this;
    }

    // ─── Verbها ───────────────────────────────────────────────────────────

    /** @param array<string, mixed>|string|null $query */
    public function get(string $url, array|string|null $query = null): Response
    {
        if (\is_array($query)) {
            $this->withQueryParameters($query);
        } elseif (\is_string($query) && $query !== '') {
            $url .= (\str_contains($url, '?') ? '&' : '?') . $query;
        }

        return $this->send('GET', $url);
    }

    public function post(string $url, mixed $data = null): Response
    {
        return $this->send('POST', $url, $data);
    }

    public function put(string $url, mixed $data = null): Response
    {
        return $this->send('PUT', $url, $data);
    }

    public function patch(string $url, mixed $data = null): Response
    {
        return $this->send('PATCH', $url, $data);
    }

    public function delete(string $url, mixed $data = null): Response
    {
        return $this->send('DELETE', $url, $data);
    }

    public function head(string $url): Response
    {
        return $this->send('HEAD', $url);
    }

    // ─── ارسال ────────────────────────────────────────────────────────────

    /**
     * ارسال کامل: SSRF → build → retry(circuit(transport + redirect re-check)).
     *
     * @param array<string, mixed> $options
     */
    public function send(string $method, string $url, mixed $data = null, array $options = []): Response
    {
        if ($options !== []) {
            $this->options = \array_merge($this->options, $options);
        }

        // حالت Pool: ثبت برای اجرای گروهی
        if ($this->pool !== null && $this->poolName !== null) {
            return $this->pool->enqueue($this->poolName, $this, $method, $url, $data);
        }

        return $this->sendReal($method, $url, $data);
    }

    /** ارسال واقعی (بدون مسیر Pool) — برای Pool و تست. */
    public function sendReal(string $method, string $url, mixed $data = null): Response
    {
        // 🔒 یک‌بار ساخت URL/هدرها/بدنه — هم برای رکورد و هم برای ارسال
        [$finalUrl, $headers, $body] = $this->prepareRaw(null, $method, $url, $data);
        $request = new Request(\strtoupper($method), $finalUrl, \array_change_key_case($headers), $body);

        // 1) حالت Fake — بدون شبکه/SSRF
        $stub = $this->factory->findStub($request);
        if ($stub !== null) {
            $this->factory->record($request, $stub);

            return $stub;
        }

        // 2) 🔒 SSRF پیش از هر چیز (DOC-064 §3)
        $this->factory->assertSsrfAllowed($finalUrl);

        // 3) retry × circuit × transport
        $retryHandler = new RetryHandler($this->tries, $this->retrySleepMs, 30_000, $this->factory->sleeper());
        $breaker = $this->factory->breaker();
        $service = $this->serviceName($finalUrl);

        $raw = $retryHandler->run(
            fn (): TransportResponse => $breaker->call($service, fn (): TransportResponse => $this->sendWithRedirects($method, $finalUrl, $headers, $body)),
            fn (TransportResponse $r): bool => $this->isRetryableStatus($r->status),
            fn (TransportResponse $r): int => $this->retryAfterSeconds($r),
        );

        $response = new Response($raw->body, $raw->status, $raw->headers, $raw->effectiveUrl !== '' ? $raw->effectiveUrl : $finalUrl);
        $this->factory->record($request, $response);

        return $response;
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /** ساخت رکورد درخواست (پیش از ارسال — برای fake/record). */
    public function buildRequestRecord(string $method, string $url, mixed $data = null): Request
    {
        [$finalUrl, $headers, $body] = $this->prepareRaw(null, $method, $url, $data);

        return new Request(\strtoupper($method), $finalUrl, \array_change_key_case($headers), $body);
    }

    /**
     * آماده‌سازی URL/هدرها/بدنه (بدون ارسال).
     *
     * @return array{0: string, 1: array<string, string>, 2: ?string}
     */
    public function prepareRaw(?Request $record = null, ?string $method = null, ?string $url = null, mixed $data = null): array
    {
        $method ??= $record?->method() ?? 'GET';
        $url ??= $record?->url() ?? '';

        $finalUrl = $this->mergeQuery($url);

        $headers = $this->headers;
        if ($this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            $headers['Cookie'] = \implode('; ', $pairs);
        }

        $body = $this->buildBody($method, $data, $headers);

        return [$finalUrl, $headers, $body];
    }

    /** حلقهٔ redirect با 🔒 SSRF re-check در هر hop (DOC-064 §3.2). */
    private function sendWithRedirects(string $method, string $url, array $headers, ?string $body): TransportResponse
    {
        $transport = $this->factory->transport();
        $currentUrl = $url;
        $currentMethod = $method;
        $currentBody = $body;
        $remaining = $this->maxRedirects;

        while (true) {
            $raw = $transport->send($currentMethod, $currentUrl, $headers, $currentBody, $this->options);

            if (!$raw->isRedirect() || $remaining <= 0) {
                if ($raw->isRedirect()) {
                    throw HttpClientException::tooManyRedirects($currentUrl);
                }

                return new TransportResponse($raw->status, $raw->headers, $raw->body, $currentUrl);
            }

            $location = $raw->header('Location') ?? $raw->header('location');
            if ($location === null || $location === '') {
                return new TransportResponse($raw->status, $raw->headers, $raw->body, $currentUrl);
            }

            $currentUrl = $this->resolveUrl($currentUrl, $location);

            // 🔒 بررسی مجدد SSRF برای هر hop
            $this->factory->assertSsrfAllowed($currentUrl);

            // 303 → GET؛ 301/302 برای POST به‌طور سنتی GET
            if ($raw->status === 303 || ($raw->status !== 307 && $raw->status !== 308 && $currentMethod !== 'GET' && $currentMethod !== 'HEAD')) {
                $currentMethod = 'GET';
                $currentBody = null;
                unset($headers['Content-Type'], $headers['Content-Length']);
            }

            $remaining--;
        }
    }

    /** @param array<string, string> $headers */
    private function buildBody(string $method, mixed $data, array &$headers): ?string
    {
        if (\in_array(\strtoupper($method), ['GET', 'HEAD'], true) && $this->files === []) {
            return null;    // GET/HEAD بدون بدنه — داده از query می‌رود
        }

        if ($this->files !== []) {
            [$body, $contentType] = $this->buildMultipart(\is_array($data) ? $data : []);
            $headers['Content-Type'] = $contentType;

            return $body;
        }

        if ($data === null || $data === []) {
            return null;
        }

        if (\is_string($data)) {
            return $data;
        }

        if ($this->bodyType === 'json' || $this->bodyType === '') {
            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/json';
            }

            return \json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        }

        // form
        return \http_build_query($data);
    }

    /** @param array<string, mixed> $fields @return array{0: string, 1: string} */
    private function buildMultipart(array $fields): array
    {
        $boundary = 'tavaan-' . \bin2hex(\random_bytes(16));
        $body = '';

        foreach ($fields as $name => $value) {
            if (\is_array($value)) {
                $value = (string) \json_encode($value, \JSON_UNESCAPED_UNICODE);
            }
            $body .= '--' . $boundary . "\r\n"
                . 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n"
                . $value . "\r\n";
        }

        foreach ($this->files as $file) {
            $contents = $file['contents'];
            if (\is_resource($contents)) {
                $contents = (string) \stream_get_contents($contents);
            }
            $body .= '--' . $boundary . "\r\n"
                . 'Content-Disposition: form-data; name="' . $file['name'] . '"; filename="' . $file['filename'] . '"' . "\r\n";
            foreach ($file['headers'] as $headerName => $headerValue) {
                if (\strtolower((string) $headerName) !== 'content-type') {
                    $body .= $headerName . ': ' . $headerValue . "\r\n";
                }
            }
            $body .= 'Content-Type: ' . (string) ($file['headers']['Content-Type'] ?? $file['headers']['content-type'] ?? 'application/octet-stream') . "\r\n\r\n"
                . $contents . "\r\n";
        }

        $body .= '--' . $boundary . "--\r\n";

        return [$body, 'multipart/form-data; boundary=' . $boundary];
    }

    private function mergeQuery(string $url): string
    {
        if ($this->query === []) {
            return $url;
        }

        $separator = \str_contains($url, '?') ? '&' : '?';

        return $url . $separator . \http_build_query($this->query);
    }

    private function isRetryableStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;   // DOC-064 §5
    }

    private function retryAfterSeconds(TransportResponse $response): int
    {
        $retryAfter = $response->header('retry-after');
        if ($retryAfter === null) {
            return 0;
        }

        if (\ctype_digit(\trim($retryAfter))) {
            return (int) $retryAfter;
        }

        $timestamp = \strtotime($retryAfter);

        return $timestamp === false ? 0 : \max(0, $timestamp - \time());
    }

    private function serviceName(string $url): string
    {
        $parts = \parse_url($url);

        return \strtolower((string) ($parts['scheme'] ?? 'http')) . '://' . (string) ($parts['host'] ?? $url);
    }

    /** ترکیب Location (نسبی/مطلق) با URL جاری. */
    private function resolveUrl(string $base, string $location): string
    {
        if (\preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = \parse_url($base);
        $scheme = (string) ($parts['scheme'] ?? 'http');
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $origin = $scheme . '://' . $host . $port;

        if (\str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $basePath = \preg_replace('#/[^/]*$#', '/', (string) ($parts['path'] ?? '/')) ?? '/';

        return $origin . $basePath . $location;
    }

    // ─── Pool (داخلی) ─────────────────────────────────────────────────────

    /** @internal توسط Pool فراخوانی می‌شود */
    public function bindPool(Pool $pool, string $name): self
    {
        $this->pool = $pool;
        $this->poolName = $name;

        return $this;
    }

    /** @internal */
    public function transportOptions(): array
    {
        return $this->options;
    }
}

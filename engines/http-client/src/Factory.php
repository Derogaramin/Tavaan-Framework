<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\HttpClient\Exceptions\HttpClientException;
use Tavaan\Engine\HttpClient\Ssrf\InternalSsrfGuard;
use Tavaan\Engine\HttpClient\Ssrf\SecuritySsrfAdapter;
use Tavaan\Engine\HttpClient\Ssrf\SsrfCheckerInterface;
use Tavaan\Engine\HttpClient\Transport\CurlTransport;
use Tavaan\Engine\HttpClient\Transport\HttpTransportInterface;
use Tavaan\Engine\HttpClient\Transport\StreamTransport;

/**
 * تَوان | Tavaan — HttpClient
 * Factory — ریشهٔ Facade (`Http::…`)؛ fluent API + Fake + Pool (DOC-064 §2).
 *
 * ```php
 * $response = Http::withToken($t)->timeout(10)->retry(3, 100)->get($url);
 * Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);
 * Http::assertSent(fn (Request $req) => $req->urlIs('api.example.com/*'));
 * ```
 *
 * Transport: curl → stream fallback (Compat::driver('http') — DOC-064 §3.1).
 * SSRF: موتور security (نرم) → InternalSsrfGuard.
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class Factory
{
    /** @var array<string, mixed> */
    private array $config;

    private ?HttpTransportInterface $transport;

    private ?SsrfCheckerInterface $ssrf;

    private CircuitBreaker $breaker;

    /** @var array<string, Response|\Closure|list<Response>>|null */
    private ?array $stubs = null;

    private bool $faking = false;

    /** @var array<string, int> مکان‌نمای sequence برای هر الگو */
    private array $stubCursors = [];

    /** @var list<array{request: Request, response: Response}> */
    private array $recorded = [];

    /** @var \Closure(int): void|null تزریق sleep برای تست */
    private ?\Closure $sleeper = null;

    public function __construct(
        ?ConfigRepository $config = null,
        private readonly ?ContainerInterface $container = null,
        ?HttpTransportInterface $transport = null,
        ?SsrfCheckerInterface $ssrf = null,
        ?CacheRepository $cache = null,
    ) {
        $this->config = (array) ($config?->get('http-client', []) ?? []);
        $this->transport = $transport;
        $this->ssrf = $ssrf;

        $cb = (array) ($this->config['circuit_breaker'] ?? []);
        $this->breaker = new CircuitBreaker(
            $cache,
            (int) ($cb['threshold'] ?? 5),
            (int) ($cb['window'] ?? 60),
            (int) ($cb['cooldown'] ?? 30),
        );
    }

    // ─── ورودی fluent (هرکدام PendingRequest تازه) ────────────────────────

    public function newRequest(): PendingRequest
    {
        return new PendingRequest($this);
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): PendingRequest
    {
        return $this->newRequest()->withHeaders($headers);
    }

    public function withHeader(string $name, string $value): PendingRequest
    {
        return $this->newRequest()->withHeader($name, $value);
    }

    public function withToken(string $token, string $type = 'Bearer'): PendingRequest
    {
        return $this->newRequest()->withToken($token, $type);
    }

    /** @param array<string, string> $cookies */
    public function withCookies(array $cookies, string $domain = ''): PendingRequest
    {
        return $this->newRequest()->withCookies($cookies, $domain);
    }

    /** @param array<string, mixed> $query */
    public function withQueryParameters(array $query): PendingRequest
    {
        return $this->newRequest()->withQueryParameters($query);
    }

    public function asJson(): PendingRequest
    {
        return $this->newRequest()->asJson();
    }

    public function asForm(): PendingRequest
    {
        return $this->newRequest()->asForm();
    }

    public function acceptJson(): PendingRequest
    {
        return $this->newRequest()->acceptJson();
    }

    public function timeout(int $seconds): PendingRequest
    {
        return $this->newRequest()->timeout($seconds);
    }

    public function retry(int $times, int $sleepMs = 100, ?callable $when = null): PendingRequest
    {
        return $this->newRequest()->retry($times, $sleepMs, $when);
    }

    public function withoutVerifying(): PendingRequest
    {
        return $this->newRequest()->withoutVerifying();
    }

    /** @param array<string, mixed> $options */
    public function withOptions(array $options): PendingRequest
    {
        return $this->newRequest()->withOptions($options);
    }

    public function attach(string $name, mixed $contents, ?string $filename = null, array $headers = []): PendingRequest
    {
        return $this->newRequest()->attach($name, $contents, $filename, $headers);
    }

    public function maxRedirects(int $max): PendingRequest
    {
        return $this->newRequest()->maxRedirects($max);
    }

    public function connectTimeout(int $seconds): PendingRequest
    {
        return $this->newRequest()->connectTimeout($seconds);
    }

    // ─── Verbها ───────────────────────────────────────────────────────────

    /** @param array<string, mixed>|string|null $query */
    public function get(string $url, array|string|null $query = null): Response
    {
        return $this->newRequest()->get($url, $query);
    }

    public function post(string $url, mixed $data = null): Response
    {
        return $this->newRequest()->post($url, $data);
    }

    public function put(string $url, mixed $data = null): Response
    {
        return $this->newRequest()->put($url, $data);
    }

    public function patch(string $url, mixed $data = null): Response
    {
        return $this->newRequest()->patch($url, $data);
    }

    public function delete(string $url, mixed $data = null): Response
    {
        return $this->newRequest()->delete($url, $data);
    }

    public function head(string $url): Response
    {
        return $this->newRequest()->head($url);
    }

    /**
     * ارسال با متد دلخواه.
     *
     * @param array<string, mixed> $options
     */
    public function send(string $method, string $url, mixed $data = null, array $options = []): Response
    {
        return $this->newRequest()->send($method, $url, $data, $options);
    }

    // ─── Pool ─────────────────────────────────────────────────────────────

    /**
     * درخواست‌های گروهی — DOC-064 §2.
     *
     * @param callable(Pool): mixed $callback
     *
     * @return array<string, Response|\Throwable>
     */
    public function pool(callable $callback): array
    {
        $pool = new Pool($this);
        $callback($pool);

        return $pool->dispatch();
    }

    // ─── Fake (تست) ───────────────────────────────────────────────────────

    /**
     * شبیه‌سازی پاسخ‌ها بدون شبکه.
     *
     * @param array<string, Response|\Closure|list<Response>>|Response|\Closure|null $stubs
     */
    public function fake(array|Response|\Closure|null $stubs = null): self
    {
        $this->faking = true;
        $this->stubs = [];
        $this->stubCursors = [];
        $this->recorded = [];

        if ($stubs === null) {
            $this->stubs['*'] = new Response('', 200);
        } elseif ($stubs instanceof Response || $stubs instanceof \Closure) {
            $this->stubs['*'] = $stubs;
        } else {
            foreach ($stubs as $pattern => $stub) {
                $this->stubs[(string) $pattern] = $stub;
            }
        }

        return $this;
    }

    /** پایان حالت Fake. */
    public function real(): self
    {
        $this->faking = false;
        $this->stubs = null;
        $this->recorded = [];

        return $this;
    }

    public function isFaking(): bool
    {
        return $this->faking;
    }

    /** ساخت Response (کمکی برای stubها). */
    public static function response(string|array $body = '', int $status = 200, array $headers = []): Response
    {
        return Response::make($body, $status, $headers);
    }

    /** یافتن stub مطابق درخواست (null = بدون fake / بدون مطابقت). */
    public function findStub(Request $request): ?Response
    {
        if (!$this->faking || $this->stubs === null) {
            return null;
        }

        foreach ($this->stubs as $pattern => $stub) {
            if (!$this->stubMatches($pattern, $request)) {
                continue;
            }

            if ($stub instanceof Response) {
                return $stub;
            }

            if ($stub instanceof \Closure) {
                $result = $this->container !== null ? $this->container->call($stub, ['request' => $request]) : $stub($request);

                return $result instanceof Response ? $result : Response::make(\is_array($result) ? $result : (string) $result);
            }

            if (\is_array($stub) && $stub !== []) {
                // sequence: مصرف به‌ترتیب، تکرار آخرین
                $cursor = $this->stubCursors[$pattern] ?? 0;
                $response = $stub[\min($cursor, \count($stub) - 1)];
                $this->stubCursors[$pattern] = $cursor + 1;

                return $response instanceof Response ? $response : Response::make((string) $response);
            }
        }

        // fake فعال ولی بدون stub مطابق → ۲۰۰ خالی (رفتار Laravel)
        return new Response('', 200);
    }

    private function stubMatches(string $pattern, Request $request): bool
    {
        return $request->urlIs($pattern);
    }

    /** @internal توسط PendingRequest */
    public function record(Request $request, Response $response): void
    {
        if (!$this->faking) {
            return;
        }

        $this->recorded[] = ['request' => $request, 'response' => $response];
    }

    /** @return list<array{request: Request, response: Response}> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    /**
     * @param callable(Request, Response): bool $callback
     *
     * @throws HttpClientException اگر مطابقتی یافت نشود
     */
    public function assertSent(callable $callback): void
    {
        foreach ($this->recorded as $entry) {
            if ($callback($entry['request'], $entry['response'])) {
                return;
            }
        }

        throw new HttpClientException('http_client.assertion_failed', ['assert' => 'assertSent']);
    }

    /** @param callable(Request, Response): bool $callback */
    public function assertNotSent(callable $callback): void
    {
        foreach ($this->recorded as $entry) {
            if ($callback($entry['request'], $entry['response'])) {
                throw new HttpClientException('http_client.assertion_failed', ['assert' => 'assertNotSent']);
            }
        }
    }

    public function assertSentCount(int $count): void
    {
        if (\count($this->recorded) !== $count) {
            throw new HttpClientException('http_client.assertion_failed', [
                'assert' => 'assertSentCount',
                'expected' => $count,
                'actual' => \count($this->recorded),
            ]);
        }
    }

    // ─── اجزا (برای PendingRequest) ───────────────────────────────────────

    public function transport(): HttpTransportInterface
    {
        return $this->transport ??= self::defaultTransport((float) ($this->config['timeout'] ?? 10), (bool) ($this->config['verify_tls'] ?? true));
    }

    /** انتخاب درایور: curl → stream (DOC-064 §3.1 / Compat::driver('http')). */
    public static function defaultTransport(float $timeout = 15.0, bool $verifyTls = true): HttpTransportInterface
    {
        $driver = \class_exists(\Tavaan\Engine\Compat\Compat::class)
            ? \Tavaan\Engine\Compat\Compat::driver('http')
            : (CurlTransport::available() ? 'curl' : 'stream');

        return $driver === 'curl' && CurlTransport::available()
            ? new CurlTransport($timeout, $verifyTls)
            : new StreamTransport($timeout, $verifyTls);
    }

    /** @throws \Tavaan\Core\Exceptions\TavaanException */
    public function assertSsrfAllowed(string $url): void
    {
        if (!(bool) ($this->config['ssrf_protection'] ?? true)) {
            return;
        }

        $this->ssrfChecker()->assertAllowed($url);
    }

    public function ssrfAllows(string $url): bool
    {
        if (!(bool) ($this->config['ssrf_protection'] ?? true)) {
            return true;
        }

        return $this->ssrfChecker()->isAllowed($url);
    }

    public function ssrfChecker(): SsrfCheckerInterface
    {
        if ($this->ssrf !== null) {
            return $this->ssrf;
        }

        // وابستگی نرم: SsrfGuard موتور security
        if ($this->container !== null && $this->container->bound('security')) {
            try {
                $security = $this->container->make('security');
                if ($security instanceof \Tavaan\Engine\Security\Security) {
                    return $this->ssrf = new SecuritySsrfAdapter($security);
                }
            } catch (\Throwable) {
                // fallthrough → guard داخلی
            }
        }

        return $this->ssrf = new InternalSsrfGuard((array) ($this->config['ssrf'] ?? []));
    }

    public function breaker(): CircuitBreaker
    {
        return $this->breaker;
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return $this->config;
    }

    public function sleeper(): ?\Closure
    {
        return $this->sleeper;
    }

    /** تزریق sleeper (تست — بدون usleep واقعی). */
    public function withSleeper(?\Closure $sleeper): self
    {
        $this->sleeper = $sleeper;

        return $this;
    }

    /** تزریق transport (تست). */
    public function withTransport(?HttpTransportInterface $transport): self
    {
        $this->transport = $transport;

        return $this;
    }

    /** تزریق SSRF checker (تست). */
    public function withSsrfChecker(?SsrfCheckerInterface $ssrf): self
    {
        $this->ssrf = $ssrf;

        return $this;
    }
}

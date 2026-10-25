<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http;

use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\UploadedFileInterface;
use Tavaan\Core\Exceptions\Http\BadRequestException;
use Tavaan\Engine\Http\Exceptions\PayloadTooLargeException;

/**
 * تَوان | Tavaan — Http
 * Request — درخواست HTTP تغییرناپذیر (Immutable) با Bagهای تفکیک‌شده.
 *
 * - هر تغییر با `withX()` یک clone جدید برمی‌گرداند (Bagها در __clone کپی می‌شوند)
 * - پارس JSON تنبل با سقف حجم/عمق (DoS protection)
 * - Method Override (_method / X-HTTP-Method-Override)
 * - 🔒 تشخیص Request Smuggling (CL + TE همزمان → 400)
 *
 * ⚠️ غیرfinal: گسترش فقط توسط `Tavaan\Engine\Validation\FormRequest` (DOC-051 §5).
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
class Request implements RequestInterface
{
    private const MAX_JSON_DEPTH = 32;

    private const MAX_CONTEXT_DEPTH = 8;

    private Uri $uri;

    private HeaderBag $headers;

    private ParameterBag $query;

    private ParameterBag $post;

    private ParameterBag $attributes;

    private ParameterBag $cookies;

    private ParameterBag $server;

    /** @var array<string, UploadedFileInterface> */
    private array $files = [];

    private ?string $rawBody = null;

    /** @var array<string, mixed>|null */
    private ?array $parsedJson = null;

    private string $methodOverride = '';

    private ?string $clientIpOverride = null;

    private ?bool $secureOverride = null;

    private ?string $hostOverride = null;

    private int $maxJsonSize = 4_194_304;

    private static bool $methodOverrideEnabled = true;

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $cookies
     * @param array<string, UploadedFileInterface> $files
     */
    protected function __construct(
        Uri $uri,
        HeaderBag $headers,
        ParameterBag $query,
        ParameterBag $post,
        ParameterBag $attributes,
        ParameterBag $cookies,
        ParameterBag $server,
        array $files,
        ?string $rawBody,
    ) {
        $this->uri = $uri;
        $this->headers = $headers;
        $this->query = $query;
        $this->post = $post;
        $this->attributes = $attributes;
        $this->cookies = $cookies;
        $this->server = $server;
        $this->files = $files;
        $this->rawBody = $rawBody;
    }

    /**
     * ساخت نمونهٔ کلاس فرزند (FormRequest) از حالت یک درخواست موجود.
     *
     * 🔒 فقط درون سلسلهٔ Request قابل فراخوانی است (protected) — دسترسی به
     * state خصوصی از scope همین کلاس مجاز است.
     *
     * @template T of Request
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected static function fromRequestInto(string $class, Request $source): Request
    {
        if (!\is_subclass_of($class, self::class)) {
            throw new \InvalidArgumentException(\sprintf('[%s] is not a Request subclass.', $class));
        }

        return new $class(
            $source->uri,
            $source->headers,
            $source->query,
            $source->post,
            $source->attributes,
            $source->cookies,
            $source->server,
            $source->files,
            $source->rawBody,
        );
    }

    /**
     * دسترسی bag ورودی POST (mutable) — برای FormRequest::merge/replace.
     *
     * @internal فقط برای زیرکلاس‌های مجاز (FormRequest)
     */
    protected function postBag(): ParameterBag
    {
        return $this->post;
    }

    // ─── ساخت ─────────────────────────────────────────────────────────────

    /** ساخت از superglobals (P7 — Request Capture). */
    public static function capture(): self
    {
        $server = $_SERVER;

        // 🔒 Request Smuggling: CL + TE همزمان ممنوع
        if (isset($server['CONTENT_LENGTH']) && $server['CONTENT_LENGTH'] !== ''
            && isset($server['HTTP_TRANSFER_ENCODING'])) {
            throw new BadRequestException('http.request.smuggling');
        }

        $headers = self::extractHeaders($server);

        $method = \strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $scheme = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') || (int) ($server['SERVER_PORT'] ?? 0) === 443
            ? 'https'
            : 'http';
        $host = (string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost');
        $port = $server['SERVER_PORT'] !== null ? (int) $server['SERVER_PORT'] : null;
        $requestUri = (string) ($server['REQUEST_URI'] ?? '/');

        // host ممکن است port داشته باشد
        if (\str_contains($host, ':')) {
            [$hostOnly, $hostPort] = \explode(':', $host, 2);
            if ($hostOnly !== '' && $hostOnly[0] !== '[') {
                $host = $hostOnly;
                $port = (int) $hostPort;
            }
        }

        $rawPath = \parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $decodedPath = \rawurldecode((string) $rawPath);
        // 🔒 double-encoding guard: اگر پس از decode هنوز %xx دارد، reject (400)
        if (\preg_match('/%[0-9a-fA-F]{2}/', $decodedPath) === 1) {
            throw new BadRequestException('http.bad_request', ['reason' => 'double_encoded_path']);
        }
        $uri = Uri::fromParts($scheme, $host, $port, $decodedPath, (string) (\parse_url($requestUri, PHP_URL_QUERY) ?? ''));

        $body = \in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            ? (string) \file_get_contents('php://input')
            : '';

        $request = new self(
            $uri,
            new HeaderBag($headers),
            new ParameterBag(self::clean($_GET)),
            new ParameterBag(self::clean($_POST)),
            new ParameterBag(),
            new ParameterBag(self::clean($_COOKIE)),
            new ParameterBag($server),
            self::normalizeFiles($_FILES),
            $body,
        );

        $request->applyMethodOverride();

        return $request;
    }

    /**
     * ساخت مصنوعی (تست/زیردرخواست HMVC).
     *
     * @param array<string, mixed>        $params  query (GET) یا body (POST)
     * @param array<string, mixed>        $cookies
     * @param array<string, mixed>        $server
     * @param array<string, UploadedFileInterface> $files
     */
    public static function create(
        string $uri,
        string $method = 'GET',
        array $params = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $body = null,
    ): self {
        $method = \strtoupper($method);
        $parsed = new Uri($uri);

        // 🔒 هم‌راستا با fromGlobals: مسیر percent-decoded می‌شود تا رفتار
        // تست درون‌فرایندی با HTTP واقعی یکسان بماند (M9 — مسیرهای فارسی
        // مثل /blog/archive/۱۴۰۳/۰۷). guard دوباره-رمزنگاری مانند fromGlobals.
        if (\str_contains($parsed->path(), '%')) {
            $decodedPath = \rawurldecode($parsed->path());
            if (\preg_match('/%[0-9a-fA-F]{2}/', $decodedPath) === 1) {
                throw new BadRequestException('http.bad_request', ['reason' => 'double_encoded_path']);
            }
            $parsed = $parsed->withPath($decodedPath);
        }

        $headers = [];
        foreach ($server as $key => $value) {
            if (\str_starts_with((string) $key, 'HTTP_')) {
                $name = \str_replace(' ', '-', \ucwords(\strtolower(\str_replace('_', ' ', \substr((string) $key, 5)))));
                $headers[$name] = (string) $value;
            }
        }

        $query = [];
        if ($parsed->query() !== '') {
            \parse_str($parsed->query(), $query);
        }
        if ($method === 'GET' || $method === 'HEAD' || $method === 'DELETE') {
            $query = [...$query, ...$params];
            $post = [];
        } else {
            $post = $params;
        }

        $serverBag = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => (string) $parsed,
            'SERVER_NAME' => $parsed->host() !== '' ? $parsed->host() : 'localhost',
            'SERVER_PORT' => (string) ($parsed->port() ?? ($parsed->scheme() === 'https' ? 443 : 80)),
            'HTTPS' => $parsed->scheme() === 'https' ? 'on' : 'off',
            'REMOTE_ADDR' => '127.0.0.1',
            ...$server,
        ];

        // 🔒 هم‌راستا با fromGlobals: اگر $cookies صریح داده نشد، هدر
        // HTTP_COOKIE پارس می‌شود — کوکی نشست در مسیر تست/زیردرخواست گم نمی‌شود (M9).
        if ($cookies === [] && isset($serverBag['HTTP_COOKIE']) && \is_string($serverBag['HTTP_COOKIE'])) {
            foreach (\explode(';', $serverBag['HTTP_COOKIE']) as $pair) {
                if (!\str_contains($pair, '=')) {
                    continue;
                }
                [$cookieName, $cookieValue] = \explode('=', $pair, 2);
                $cookieName = \trim($cookieName);
                if ($cookieName !== '') {
                    $cookies[$cookieName] = \rawurldecode(\trim($cookieValue));
                }
            }
        }

        if ($body === null && $post !== []) {
            $isJson = isset($headers['Content-Type']) && \str_contains((string) $headers['Content-Type'], 'json');
            $body = $isJson
                ? (string) \json_encode($post, JSON_UNESCAPED_UNICODE)
                : \http_build_query($post);
        }

        $request = new self(
            $parsed,
            new HeaderBag($headers),
            new ParameterBag(self::clean($query)),
            new ParameterBag(self::clean($post)),
            new ParameterBag(),
            new ParameterBag(self::clean($cookies)),
            new ParameterBag($serverBag),
            $files,
            $body ?? '',
        );

        $request->applyMethodOverride();

        return $request;
    }

    /** فعال/غیرفعال‌سازی method override (از config توسط Provider). */
    public static function enableMethodOverride(bool $enabled): void
    {
        self::$methodOverrideEnabled = $enabled;
    }

    public function withMaxJsonSize(int $bytes): void
    {
        $this->maxJsonSize = $bytes;
    }

    // ─── متد ──────────────────────────────────────────────────────────────

    public function method(): string
    {
        return $this->methodOverride !== '' ? $this->methodOverride : $this->realMethod();
    }

    public function realMethod(): string
    {
        return \strtoupper((string) $this->server->get('REQUEST_METHOD', 'GET'));
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === \strtoupper($method);
    }

    public function withMethod(string $method): static
    {
        $clone = clone $this;
        $clone->methodOverride = \strtoupper($method);

        return $clone;
    }

    // ─── URL ──────────────────────────────────────────────────────────────

    public function uri(): Uri
    {
        $uri = $this->uri;
        if ($this->hostOverride !== null) {
            $uri = $uri->withHost($this->hostOverride);
        }
        if ($this->secureOverride !== null) {
            $uri = $uri->withScheme($this->secureOverride ? 'https' : 'http');
        }

        return $uri;
    }

    public function path(): string
    {
        return $this->uri->path();
    }

    public function url(): string
    {
        return (string) $this->uri();
    }

    public function fullUrl(): string
    {
        $uri = $this->uri();
        $query = $this->query->all();
        if ($query !== []) {
            $uri = $uri->withQuery($query);
        }

        return (string) $uri;
    }

    /** @param array<string, mixed> $query */
    public function fullUrlWithQuery(array $query): string
    {
        return (string) $this->uri()->withQuery([...$this->query->all(), ...$query]);
    }

    public function scheme(): string
    {
        return $this->uri()->scheme();
    }

    public function host(): string
    {
        return $this->uri()->host();
    }

    public function port(): ?int
    {
        return $this->uri()->port();
    }

    public function isSecure(): bool
    {
        return $this->uri()->scheme() === 'https';
    }

    /** تطبیق مسیر با wildcard (`shop/*`). */
    public function is(string ...$patterns): bool
    {
        $path = \trim($this->path(), '/');
        foreach ($patterns as $pattern) {
            $regex = '/^' . \str_replace('\*', '.*', \preg_quote(\trim($pattern, '/'), '/')) . '$/u';
            if (\preg_match($regex, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $query */
    public function withQuery(array $query): static
    {
        $clone = clone $this;
        $clone->query = new ParameterBag($query);
        $clone->uri = $this->uri->withQuery($query);

        return $clone;
    }

    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->uri = $this->uri->withPath($path);

        return $clone;
    }

    // ─── Header ───────────────────────────────────────────────────────────

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers->get($key, $default);
    }

    /** @return array<string, list<string>> */
    public function headers(): array
    {
        return $this->headers->allPreserveCase();
    }

    public function headerBag(): HeaderBag
    {
        return $this->headers;
    }

    public function hasHeader(string $key): bool
    {
        return $this->headers->has($key);
    }

    public function withHeader(string $key, string $value): static
    {
        $clone = clone $this;
        $clone->headers = clone $this->headers;
        $clone->headers->set($key, $value);

        return $clone;
    }

    public function userAgent(): ?string
    {
        return $this->headers->get('User-Agent');
    }

    public function accept(): ?string
    {
        return $this->headers->get('Accept');
    }

    public function acceptLanguage(): ?string
    {
        return $this->headers->get('Accept-Language');
    }

    public function contentType(): ?string
    {
        return $this->headers->get('Content-Type');
    }

    public function referer(): ?string
    {
        return $this->headers->get('Referer');
    }

    public function bearerToken(): ?string
    {
        $header = (string) ($this->headers->get('Authorization') ?? '');
        if (\preg_match('/^Bearer\s+(\S+)$/i', $header, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    public function expectsJson(): bool
    {
        $accept = (string) ($this->headers->get('Accept') ?? '');
        $wants = \str_contains($accept, 'json') || $accept === '*/*' || $accept === '';

        return ($this->wantsJson() || $this->is('api/*')) && $wants
            || ($this->headers->get('X-Requested-With') === 'XMLHttpRequest' && $wants);
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($this->headers->get('Accept') ?? '');

        return \str_contains($accept, 'json');
    }

    // ─── ورودی ────────────────────────────────────────────────────────────

    /** اولویت: query < post < json (DOC-039 T-04). */
    public function input(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return \Tavaan\Support\Arr::get($all, $key, $default);
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query->all() : $this->query->get($key, $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->post->all() : $this->post->get($key, $default);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $json = $this->json();

        return [...$this->query->all(), ...$this->post->all(), ...\is_array($json) ? $json : []];
    }

    /** @param list<string> $keys @return array<string, mixed> */
    public function only(array $keys): array
    {
        return \Tavaan\Support\Arr::only($this->all(), $keys);
    }

    /** @param list<string> $keys @return array<string, mixed> */
    public function except(array $keys): array
    {
        return \Tavaan\Support\Arr::except($this->all(), $keys);
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->all());
    }

    /** @param list<string> $keys */
    public function hasAny(array $keys): bool
    {
        $all = $this->all();
        foreach ($keys as $key) {
            if (\array_key_exists($key, $all)) {
                return true;
            }
        }

        return false;
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);

        return $value !== null && $value !== '' && $value !== [];
    }

    public function missing(string $key): bool
    {
        return !$this->has($key);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        return $value === null ? $default : \filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return \is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key);

        return \is_numeric($value) ? (float) $value : $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return \is_scalar($value) ? (string) $value : $default;
    }

    /** @return array<string, mixed> */
    public function array(string $key): array
    {
        $value = $this->input($key, []);

        return \is_array($value) ? $value : [];
    }

    public function body(): string
    {
        return $this->rawBody ?? '';
    }

    /**
     * پارس JSON بدنه (تنبل، امن).
     *
     * @return array<string, mixed>
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->parsedJson === null) {
            $this->parsedJson = $this->parseJsonBody();
        }

        if ($key === null) {
            return $this->parsedJson;
        }

        return \Tavaan\Support\Arr::get($this->parsedJson, $key, $default);
    }

    // ─── فایل/کوکی/attribute ──────────────────────────────────────────────

    public function file(string $key): ?UploadedFileInterface
    {
        return $this->files[$key] ?? null;
    }

    /** @return array<string, UploadedFileInterface> */
    public function files(): array
    {
        return $this->files;
    }

    public function hasFile(string $key): bool
    {
        $file = $this->file($key);

        return $file !== null && $file->isValid();
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies->get($key, $default);
    }

    /** @return array<string, mixed> */
    public function cookies(): array
    {
        return $this->cookies->all();
    }

    /** @param array<string, mixed> $cookies */
    public function withCookies(array $cookies): static
    {
        $clone = clone $this;
        $clone->cookies = new ParameterBag($cookies);

        return $clone;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes->get($key, $default);
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes->all();
    }

    public function withAttribute(string $key, mixed $value): static
    {
        $clone = clone $this;
        $clone->attributes = clone $this->attributes;
        $clone->attributes->set($key, $value);

        return $clone;
    }

    // ─── روت ──────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $params */
    public function withRoute(object $route, array $params = []): static
    {
        $clone = $this->withAttribute('route', $route);
        foreach ($params as $name => $value) {
            $clone = $clone->withAttribute('routeParam_' . $name, $value);
        }

        return $clone;
    }

    public function routeParameter(string $key, mixed $default = null): mixed
    {
        return $this->attributes->get('routeParam_' . $key, $default);
    }

    /** @return array<string, mixed> */
    public function routeParameters(): array
    {
        $out = [];
        foreach ($this->attributes->all() as $key => $value) {
            if (\str_starts_with($key, 'routeParam_')) {
                $out[\substr($key, 11)] = $value;
            }
        }

        return $out;
    }

    public function routeIs(string ...$names): bool
    {
        $route = $this->attributes->get('route');
        if ($route === null || !\method_exists($route, 'getName')) {
            return false;
        }
        $current = (string) $route->getName();
        foreach ($names as $pattern) {
            $regex = '/^' . \str_replace('\*', '.*', \preg_quote($pattern, '/')) . '$/';
            if (\preg_match($regex, $current) === 1) {
                return true;
            }
        }

        return false;
    }

    // ─── کلاینت/Proxy ─────────────────────────────────────────────────────

    public function ip(): ?string
    {
        if ($this->clientIpOverride !== null) {
            return $this->clientIpOverride;
        }
        $addr = $this->server->get('REMOTE_ADDR');

        return \is_string($addr) ? $addr : null;
    }

    /** @return list<string> */
    public function ips(): array
    {
        $chain = [];
        $forwarded = (string) ($this->headers->get('X-Forwarded-For') ?? '');
        if ($forwarded !== '') {
            $chain = \array_map(\trim(...), \explode(',', $forwarded));
        }
        $remote = $this->ip();

        return $remote === null ? $chain : [...$chain, $remote];
    }

    public function withClientIp(string $ip): static
    {
        $clone = clone $this;
        $clone->clientIpOverride = $ip;

        return $clone;
    }

    public function withSecure(bool $secure): static
    {
        $clone = clone $this;
        $clone->secureOverride = $secure;

        return $clone;
    }

    public function withHost(string $host): static
    {
        $clone = clone $this;
        $clone->hostOverride = $host;

        return $clone;
    }

    // ─── Session (نرم) ────────────────────────────────────────────────────

    public function session(): ?object
    {
        $session = $this->attributes->get('session');

        return \is_object($session) ? $session : null;
    }

    public function hasSession(): bool
    {
        return $this->attributes->get('session') !== null;
    }

    /** ورودی قدیمی flash شده در session (برای فرم‌ها). */
    public function old(string $key, mixed $default = null): mixed
    {
        $session = $this->session();
        if ($session === null || !\method_exists($session, 'getOldInput')) {
            return $default;
        }

        return $session->getOldInput($key, $default);
    }

    // ─── زمان/Debug ───────────────────────────────────────────────────────

    public function requestTime(): float
    {
        return (float) $this->server->get('REQUEST_TIME_FLOAT', \defined('TVN_START') ? TVN_START : \microtime(true));
    }

    public function elapsedMs(): float
    {
        return (\microtime(true) - $this->requestTime()) * 1000;
    }

    public function server(): ParameterBag
    {
        return $this->server;
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    public function __clone(): void
    {
        $this->headers = clone $this->headers;
        $this->query = clone $this->query;
        $this->post = clone $this->post;
        $this->attributes = clone $this->attributes;
        $this->cookies = clone $this->cookies;
        $this->server = clone $this->server;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonBody(): array
    {
        $raw = $this->body();
        if ($raw === '') {
            return [];
        }

        $contentType = (string) ($this->headers->get('Content-Type') ?? '');
        if (!\str_contains($contentType, 'json') && $raw[0] !== '{' && $raw[0] !== '[') {
            return [];
        }

        if (\strlen($raw) > $this->maxJsonSize) {
            throw PayloadTooLargeException::for(\strlen($raw), $this->maxJsonSize);
        }

        try {
            $decoded = \json_decode($raw, true, self::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new BadRequestException('http.json.invalid', ['error' => $e->getMessage()], 'بدنهٔ درخواست JSON معتبر نیست.');
        }

        $this->assertDepth(\is_array($decoded) ? $decoded : []);

        return \is_array($decoded) ? $decoded : ['_value' => $decoded];
    }

    /** @param array<array-key, mixed> $data */
    private function assertDepth(array $data, int $depth = 0): void
    {
        if ($depth > self::MAX_CONTEXT_DEPTH) {
            throw new BadRequestException('http.json.invalid', ['reason' => 'depth'], 'عمق JSON بیش از حد مجاز است.');
        }
        foreach ($data as $value) {
            if (\is_array($value)) {
                $this->assertDepth($value, $depth + 1);
            }
        }
    }

    private function applyMethodOverride(): void
    {
        if (!self::$methodOverrideEnabled) {
            return;
        }
        if ($this->realMethod() !== 'POST') {
            return;
        }

        $override = $this->headers->get('X-HTTP-Method-Override');
        if ($override === null) {
            $override = $this->post->get('_method');
        }
        if (\is_string($override) && $override !== '') {
            $override = \strtoupper($override);
            if (\in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $this->methodOverride = $override;
            }
        }
    }

    /**
     * @param array<string, mixed> $server
     *
     * @return array<string, string>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            $key = (string) $key;
            if (\str_starts_with($key, 'HTTP_')) {
                $name = \str_replace(' ', '-', \ucwords(\strtolower(\str_replace('_', ' ', \substr($key, 5)))));
                $headers[$name] = (string) $value;
                continue;
            }
            if (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = \str_replace(' ', '-', \ucwords(\strtolower(\str_replace('_', ' ', $key))));
                $headers[$name] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * پاک‌سازی control chars از ورودی‌های رشته‌ای (sanitize).
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function clean(array $data, int $depth = 0): array
    {
        if ($depth > self::MAX_CONTEXT_DEPTH) {
            return [];
        }

        $out = [];
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $out[$key] = self::clean($value, $depth + 1);
                continue;
            }
            if (\is_string($value)) {
                $value = (string) \preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
                if (\strlen($value) > 1_048_576) {
                    $value = \substr($value, 0, 1_048_576);
                }
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param array<array-key, mixed> $files
     *
     * @return array<string, UploadedFileInterface>
     */
    private static function normalizeFiles(array $files): array
    {
        $out = [];
        foreach ($files as $key => $file) {
            if (!\is_array($file) || !isset($file['name'])) {
                continue;
            }
            if (\is_array($file['name'])) {
                $count = \count($file['name']);
                for ($i = 0; $i < $count; $i++) {
                    $out[(string) $key . '.' . $i] = new UploadedFile(
                        (string) $file['tmp_name'][$i],
                        (string) $file['name'][$i],
                        (string) ($file['type'][$i] ?? ''),
                        (int) ($file['size'][$i] ?? 0),
                        (int) ($file['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                    );
                }
                continue;
            }
            $out[(string) $key] = new UploadedFile(
                (string) $file['tmp_name'],
                (string) $file['name'],
                (string) ($file['type'] ?? ''),
                (int) ($file['size'] ?? 0),
                (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
            );
        }

        return $out;
    }
}

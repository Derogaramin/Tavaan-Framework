<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

/**
 * تَوان | Tavaan — Routing
 * Route — یک روت با Fluent API (name/middleware/where/defaults/domain).
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class Route
{
    /**
     * بیت‌ماسک متدهای استاندارد (بهینه‌سازی حافظه — DOC-040 §12).
     * آرایهٔ list<string> در PHP حداقل ~۱۸۴ بایت هزینه دارد؛ int صفر بایت.
     */
    private const METHOD_BITS = [
        'GET' => 1,
        'HEAD' => 2,
        'POST' => 4,
        'PUT' => 8,
        'PATCH' => 16,
        'DELETE' => 32,
        'OPTIONS' => 64,
    ];

    private const BIT_METHODS = [
        1 => 'GET',
        2 => 'HEAD',
        4 => 'POST',
        8 => 'PUT',
        16 => 'PATCH',
        32 => 'DELETE',
        64 => 'OPTIONS',
    ];

    /** متدهای HTTP استاندارد (uppercase) — بیت‌ماسک */
    private int $methodBits = 0;

    /** @var list<string> متدهای غیراستاندارد (نادر — فقط در صورت نیاز allocate می‌شود) */
    private array $extraMethods = [];

    private string $uri;

    /** @var \Closure|string|array{0: class-string|object, 1: string} */
    private mixed $action;

    private string $name = '';

    private string $namePrefix = '';

    /** @var list<string> */
    private array $middleware = [];

    /** @var array<string, string> param → regex */
    private array $wheres = [];

    /** @var array<string, mixed> */
    private array $defaults = [];

    /** @var array<string, string> param → binding field (مانند {product:slug}) */
    private array $bindingFields = [];

    private string $domain = '';

    private int $order = 0;

    /** آیا شمارنده‌های segments حداقل یک‌بار محاسبه شده‌اند؟ (پارس تنبل) */
    private bool $parsed = false;

    private int $staticCount = 0;

    private int $paramCount = 0;

    private int $constrainedCount = 0;

    private bool $hasWildcard = false;

    /**
     * @param list<string> $methods
     * @param \Closure|class-string|array{0: class-string|object, 1: string} $action
     */
    public function __construct(array $methods, string $uri, mixed $action)
    {
        foreach ($methods as $method) {
            $method = \strtoupper((string) $method);
            $bit = self::METHOD_BITS[$method] ?? 0;
            if ($bit !== 0) {
                $this->methodBits |= $bit;
            } elseif (!\in_array($method, $this->extraMethods, true)) {
                $this->extraMethods[] = $method;
            }
        }
        if (($this->methodBits & 1) !== 0 && ($this->methodBits & 2) === 0) {
            $this->methodBits |= 2;   // RFC 9110: HEAD همراه GET
        }
        $this->uri = '/' . \trim($uri, '/');
        $this->action = $action;
        // پارس segments تنبل است (ensureParsed) — ساخت/بارگذاری کش سریع‌تر
    }

    // ─── Fluent ───────────────────────────────────────────────────────────

    /** نام روت = prefix گروه + نام (رفتار Laravel: همیشه الحاق). */
    public function name(string $name): self
    {
        $this->name = $this->namePrefix . $name;

        return $this;
    }

    /** @param list<string>|string $middleware */
    public function middleware(array|string $middleware): self
    {
        foreach ((array) $middleware as $mw) {
            $this->middleware[] = (string) $mw;
        }

        return $this;
    }

    /** @param list<string>|string $middleware */
    public function withoutMiddleware(array|string $middleware): self
    {
        $this->middleware = \array_values(\array_diff($this->middleware, (array) $middleware));

        return $this;
    }

    public function where(string $param, string $regex): self
    {
        $this->wheres[$param] = $regex;
        $this->parseSegments();

        return $this;
    }

    /** @param array<string, string> $wheres */
    public function whereAll(array $wheres): self
    {
        foreach ($wheres as $param => $regex) {
            $this->where($param, $regex);
        }

        return $this;
    }

    public function whereNumber(string $param): self
    {
        return $this->where($param, '[0-9]+');
    }

    public function whereAlpha(string $param): self
    {
        return $this->where($param, '[A-Za-z]+');
    }

    public function whereAlphanumeric(string $param): self
    {
        return $this->where($param, '[A-Za-z0-9]+');
    }

    public function whereUuid(string $param): self
    {
        return $this->where($param, '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
    }

    public function whereUlid(string $param): self
    {
        return $this->where($param, '[0-9A-Za-z]{26}');
    }

    public function whereSlug(string $param): self
    {
        return $this->where($param, '[a-z0-9]+(?:-[a-z0-9]+)*');
    }

    /** @param list<string> $values */
    public function whereIn(string $param, array $values): self
    {
        return $this->where($param, '(?:' . \implode('|', \array_map(static fn (string $v): string => \preg_quote($v, '#'), $values)) . ')');
    }

    public function defaults(string $param, mixed $value): self
    {
        $this->defaults[$param] = $value;

        return $this;
    }

    public function domain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function setOrder(int $order): void
    {
        $this->order = $order;
    }

    // ─── Getters ──────────────────────────────────────────────────────────

    /** @return list<string> متدهای HTTP (ترتیب متعارف؛ HEAD همراه GET) */
    public function methods(): array
    {
        $out = [];
        foreach (self::BIT_METHODS as $bit => $name) {
            if (($this->methodBits & $bit) !== 0) {
                $out[] = $name;
            }
        }
        foreach ($this->extraMethods as $method) {
            $out[] = $method;
        }

        return $out;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function action(): mixed
    {
        return $this->action;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function named(string $name): bool
    {
        return $this->name === $name;
    }

    /** @return list<string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /** @return array<string, string> */
    public function wheres(): array
    {
        return $this->wheres;
    }

    /** @return array<string, mixed> */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function order(): int
    {
        return $this->order;
    }

    /** @return list<string> نام پارامترها */
    public function parameterNames(): array
    {
        $names = [];
        foreach ($this->parseSegments() as $segment) {
            if ($segment instanceof RouteSegment) {
                $names[] = $segment->name;
            }
        }

        return $names;
    }

    /** @return array<string, string> */
    public function bindingFields(): array
    {
        return $this->bindingFields;
    }

    /**
     * نمایش فشردهٔ بخش‌ها (مصرف داخلی: RouteCompiler/UrlGenerator).
     * در هر فراخوانی fresh پارس می‌شود و نگه‌داشته نمی‌شود —
     * بهینه‌سازی حافظه (DOC-040 §12: ۱۰۰۰ روت ≤ ۲MB).
     *
     * @return list<string|RouteSegment>
     */
    public function rawSegments(): array
    {
        return $this->parseSegments();
    }

    /**
     * نمایش سازگار با API قدیمی (materialize در هر فراخوانی).
     *
     * @return list<array{name?: string, regex?: string, static?: string, wildcard?: bool}>
     */
    public function segments(): array
    {
        $out = [];
        foreach ($this->parseSegments() as $segment) {
            $out[] = $segment instanceof RouteSegment
                ? ['name' => $segment->name, 'regex' => $segment->regex, 'wildcard' => $segment->wildcard]
                : ['static' => $segment];
        }

        return $out;
    }

    public function hasWildcard(): bool
    {
        $this->ensureParsed();

        return $this->hasWildcard;
    }

    /**
     * امتیاز specificity (DOC-040 §5.4) — هرچه بیشتر، اولویت بالاتر.
     */
    public function specificity(): int
    {
        $this->ensureParsed();

        return 1000 * $this->staticCount
            + 100 * $this->constrainedCount
            + 10 * ($this->paramCount - $this->constrainedCount)
            + ($this->domain !== '' ? 1 : 0)
            - ($this->hasWildcard ? 500 : 0);
    }

    // ─── Group merge (توسط RouteRegistrar) ────────────────────────────────

    public function prependPrefix(string $prefix): void
    {
        $prefix = \trim($prefix, '/');
        if ($prefix !== '') {
            $this->uri = '/' . $prefix . $this->uri;
            $this->parseSegments();
        }
    }

    public function prependName(string $prefix): void
    {
        $this->namePrefix = $prefix . $this->namePrefix;
        if ($this->name !== '') {
            $this->name = $prefix . $this->name;
        }
    }

    /** @param list<string> $middleware */
    public function prependMiddleware(array $middleware): void
    {
        $this->middleware = \array_values([...$middleware, ...$this->middleware]);
    }

    public function setDomainIfEmpty(string $domain): void
    {
        if ($this->domain === '' && $domain !== '') {
            $this->domain = $domain;
        }
    }

    /** @param array<string, string> $wheres */
    public function mergeWheres(array $wheres): void
    {
        $this->wheres = [...$wheres, ...$this->wheres];
        $this->parseSegments();
    }

    // ─── Cache serialization ──────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->action instanceof \Closure) {
            throw new \LogicException('Routes with Closure actions cannot be cached (route:cache).');
        }
        // wheres/بایندینگ‌های inline ({id:[0-9]+} یا {product:slug}) باید materialize شوند
        $this->ensureParsed();

        return [
            'methods' => $this->methods(),
            'uri' => $this->uri,
            'action' => $this->action,
            'name' => $this->name,
            'middleware' => $this->middleware,
            'wheres' => $this->wheres,
            'defaults' => $this->defaults,
            'bindingFields' => $this->bindingFields,
            'domain' => $this->domain,
            'order' => $this->order,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $route = new self((array) $data['methods'], (string) $data['uri'], $data['action']);
        $route->name = (string) ($data['name'] ?? '');
        $route->middleware = (array) ($data['middleware'] ?? []);
        $route->wheres = (array) ($data['wheres'] ?? []);
        $route->defaults = (array) ($data['defaults'] ?? []);
        $route->bindingFields = (array) ($data['bindingFields'] ?? []);
        $route->domain = (string) ($data['domain'] ?? '');
        $route->order = (int) ($data['order'] ?? 0);
        $route->parseSegments();

        return $route;
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /** پارس یک‌بارهٔ شمارنده‌ها (specificity/hasWildcard/toArray) در صورت نیاز. */
    private function ensureParsed(): void
    {
        if (!$this->parsed) {
            $this->parseSegments();
        }
    }

    /**
     * پارس uri → segments (static/param/wildcard) + inline regex/binding field.
     * شمارنده‌ها (برای specificity) و mutationهای wheres/bindingFields را
     * به‌روز می‌کند ولی خود list را نگه نمی‌دارد (حافظه — DOC-040 §12).
     *
     * @return list<string|RouteSegment>
     */
    private function parseSegments(): array
    {
        $segments = [];
        $staticCount = 0;
        $paramCount = 0;
        $constrainedCount = 0;
        $hasWildcard = false;

        $path = \trim($this->uri, '/');
        if ($path !== '') {
            foreach (\explode('/', $path) as $part) {
                if (\preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)(?::(.+))?\}$/s', $part, $m) === 1) {
                    $name = $m[1];
                    $inline = $m[2] ?? null;

                    // {product:slug} → binding field (اگر regex نباشد)
                    if ($inline !== null && !isset($this->wheres[$name]) && !self::looksLikeRegex($inline)) {
                        $this->bindingFields[$name] = $inline;
                        $inline = null;
                    }
                    if ($inline !== null) {
                        $this->wheres[$name] ??= $inline;
                    }

                    $regex = $this->wheres[$name] ?? null;
                    if ($regex !== null && self::isMultiSegmentRegex($regex)) {
                        $hasWildcard = true;   // wildcard segment (مانند .* یا شامل /)
                        $segments[] = new RouteSegment($name, $regex, true);
                        break;   // بقیهٔ مسیر بلعیده می‌شود
                    }

                    $segments[] = new RouteSegment($name, $regex);
                    $paramCount++;
                    if ($regex !== null) {
                        $constrainedCount++;
                    }
                    continue;
                }

                $segments[] = $part;
                $staticCount++;
            }
        }

        $this->staticCount = $staticCount;
        $this->paramCount = $paramCount;
        $this->constrainedCount = $constrainedCount;
        $this->hasWildcard = $hasWildcard;
        $this->parsed = true;

        return $segments;
    }

    private static function looksLikeRegex(string $value): bool
    {
        return \preg_match('/[\\\\\[\]().*+?^$|{}]/', $value) === 1;
    }

    /** آیا regex می‌تواند چند segment را ببلعد؟ (wildcard edge) */
    private static function isMultiSegmentRegex(string $regex): bool
    {
        if ($regex === '.*' || $regex === '.+') {
            return true;
        }
        // شامل / صریح (مثل files/.* یا a/b)
        if (\str_contains($regex, '/')) {
            return true;
        }
        // class منفی که / را شامل شود (مثل [^a]+) — محافظه‌کارانه فقط الگوهای رایج
        return false;
    }
}

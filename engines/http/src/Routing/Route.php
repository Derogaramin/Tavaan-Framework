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
    /** @var list<string> متدهای HTTP (uppercase) */
    private array $methods;

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

    /** @var list<array{name?: string, regex?: string, static?: string}> بخش‌های پارس‌شده */
    private array $segments = [];

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
        $this->methods = \array_values(\array_unique(\array_map(\strtoupper(...), $methods)));
        if (\in_array('GET', $this->methods, true) && !\in_array('HEAD', $this->methods, true)) {
            $this->methods[] = 'HEAD';   // RFC 9110: HEAD همراه GET
        }
        $this->uri = '/' . \trim($uri, '/');
        $this->action = $action;
        $this->parseSegments();
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

    /** @return list<string> */
    public function methods(): array
    {
        return $this->methods;
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
        foreach ($this->segments as $segment) {
            if (isset($segment['name'])) {
                $names[] = $segment['name'];
            }
        }

        return $names;
    }

    /** @return array<string, string> */
    public function bindingFields(): array
    {
        return $this->bindingFields;
    }

    /** @return list<array{name?: string, regex?: string, static?: string, wildcard?: bool}> */
    public function segments(): array
    {
        return $this->segments;
    }

    public function hasWildcard(): bool
    {
        return $this->hasWildcard;
    }

    /**
     * امتیاز specificity (DOC-040 §5.4) — هرچه بیشتر، اولویت بالاتر.
     */
    public function specificity(): int
    {
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

        return [
            'methods' => $this->methods,
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

    /** پارس uri → segments (static/param/wildcard) + inline regex/binding field. */
    private function parseSegments(): void
    {
        $this->segments = [];
        $this->staticCount = 0;
        $this->paramCount = 0;
        $this->constrainedCount = 0;
        $this->hasWildcard = false;

        $path = \trim($this->uri, '/');
        if ($path === '') {
            return;
        }

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
                    $this->hasWildcard = true;   // wildcard segment (مانند .* یا شامل /)
                    $this->segments[] = ['name' => $name, 'regex' => $regex, 'wildcard' => true];
                    break;   // بقیهٔ مسیر بلعیده می‌شود
                }

                $this->segments[] = ['name' => $name, 'regex' => $regex, 'wildcard' => false];
                $this->paramCount++;
                if ($regex !== null) {
                    $this->constrainedCount++;
                }
                continue;
            }

            $this->segments[] = ['static' => $part];
            $this->staticCount++;
        }
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

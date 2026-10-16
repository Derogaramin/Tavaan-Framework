<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container;

use Tavaan\Core\Container\Binding;
use Tavaan\Core\Container\Lifetime;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Container\ContextualBindingBuilder as ContextualBindingBuilderInterface;
use Tavaan\Engine\Container\Exceptions\AmbiguousUnionException;
use Tavaan\Engine\Container\Exceptions\BindingResolutionException;
use Tavaan\Engine\Container\Exceptions\CircularDependencyException;
use Tavaan\Engine\Container\Exceptions\ScopeNotActiveException;
use Tavaan\Engine\Container\Exceptions\UntypedParameterException;

/**
 * تَوان | Tavaan — Container
 * Container — ظرف تزریق وابستگی کامل، سریع و صفر‌وابستگی.
 *
 * قابلیت‌ها: Bind/Singleton/Scoped/Factory، Auto-Wiring بازگشتی با ReflectionCache،
 * Contextual Binding، Tagging، Extender/Decorator، Method Injection، تشخیص چرخه
 * (با مسیر کامل)، Union/DNF deterministic، Scope پشته‌ای و Lazy Proxy.
 *
 * ### مثال
 * ```php
 * $c = new Container();
 * $c->singleton(GreeterInterface::class, PersianGreeter::class);
 * $service = $c->make(WelcomeService::class);   // auto-wire
 * ```
 *
 * @api 1.0
 * @since 1.0.0
 * @see docs/03-engines/container.md
 */
class Container implements ContainerInterface
{
    /** حداکثر عمق زنجیرهٔ ساخت (DoS protection) */
    private const MAX_DEPTH = 32;

    /** sentinel برای «ناتوان در resolve» در Union */
    private const UNRESOLVED = "\0__tavaan_unresolved__\0";

    /** @var array<string, Binding> */
    protected array $bindings = [];

    /** @var array<string, object> نمونه‌های Singleton/instance */
    protected array $instances = [];

    /** @var array<string, string> alias → abstract */
    protected array $aliases = [];

    /** @var array<string, list<string>> tag → abstracts */
    protected array $tags = [];

    /** @var array<string, array<string, \Closure|class-string|array>> consumer → (abstract → impl) */
    protected array $contextual = [];

    /** @var array<string, list<\Closure>> */
    protected array $extenders = [];

    /** @var array<string, true> */
    protected array $resolved = [];

    /** @var array<string, int> پشتهٔ ساخت (abstract → depth) برای تشخیص چرخه */
    protected array $buildStack = [];

    protected ReflectionCache $reflectionCache;

    protected ScopeManager $scopeManager;

    private int $makeCalls = 0;

    private int $builds = 0;

    private int $singletonHits = 0;

    private int $scopedHits = 0;

    private bool $dispatchingEvent = false;

    /** @param array<string, mixed> $config تنظیمات strict/stats ( engines/container/config/container.php ) */
    public function __construct(protected readonly array $config = [])
    {
        $this->reflectionCache = new ReflectionCache();
        $this->scopeManager = new ScopeManager();

        $reflectionFile = $config['reflection_cache']['file'] ?? null;
        if (($config['reflection_cache']['enabled'] ?? false) && \is_string($reflectionFile)) {
            $this->reflectionCache->load($reflectionFile);
        }
    }

    // ─── ثبت (Binding) ────────────────────────────────────────────────────

    public function bind(string $abstract, \Closure|string|null $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = new Binding(
            $concrete ?? $abstract,
            $shared ? Lifetime::Singleton : Lifetime::Transient,
        );
        $this->forgetInstance($abstract);
    }

    public function singleton(string $abstract, \Closure|string|null $concrete = null): void
    {
        $this->bindings[$abstract] = new Binding($concrete ?? $abstract, Lifetime::Singleton);
    }

    public function scoped(string $abstract, \Closure|string|null $concrete = null): void
    {
        $this->bindings[$abstract] = new Binding($concrete ?? $abstract, Lifetime::Scoped);
    }

    public function factory(string $abstract, \Closure $factory): void
    {
        $this->bindings[$abstract] = new Binding($factory, Lifetime::Factory);
    }

    public function instance(string $abstract, object $instance): object
    {
        $this->instances[$abstract] = $instance;
        $this->resolved[$abstract] = true;

        return $instance;
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function tag(array|string $abstracts, string ...$tags): void
    {
        foreach ((array) $abstracts as $abstract) {
            foreach ($tags as $tag) {
                $this->tags[$tag][] = $abstract;
            }
        }
    }

    /**
     * Resolve تنبل اعضای تگ — تا iterate نشود هیچ make() رخ نمی‌دهد (T-08).
     *
     * @return \Generator<int, object>
     */
    public function tagged(string $tag): iterable
    {
        foreach ($this->tags[$tag] ?? [] as $abstract) {
            yield $this->make($abstract);
        }
    }

    public function when(string $consumer): ContextualBindingBuilderInterface
    {
        return new ContextualBindingBuilder(
            function (string $consumerClass, string $abstract, \Closure|string|array $impl): void {
                $this->contextual[$consumerClass][$abstract] = $impl;
            },
            $consumer,
        );
    }

    public function extend(string $abstract, \Closure $extender): void
    {
        $abstract = $this->resolveAlias($abstract);
        $this->extenders[$abstract][] = $extender;

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $extender($this->instances[$abstract], $this);
        }
    }

    // ─── Resolve ──────────────────────────────────────────────────────────

    public function make(string $abstract, array $parameters = []): mixed
    {
        $this->makeCalls++;
        $abstract = $this->resolveAlias($abstract);
        $this->assertSafeAbstract($abstract);

        // 1) نمونهٔ Singleton آماده
        if ($parameters === [] && isset($this->instances[$abstract])) {
            $this->singletonHits++;

            return $this->instances[$abstract];
        }

        // 2) Scope فعال
        if ($parameters === []) {
            $scoped = $this->scopeManager->find($abstract);
            if ($scoped !== null) {
                $this->scopedHits++;

                return $scoped;
            }
        }

        $this->fireResolving($abstract);

        // 3) Binding
        $lifetime = Lifetime::Transient;
        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];
            $lifetime = $binding->lifetime;

            if ($lifetime === Lifetime::Factory) {
                \assert($binding->concrete instanceof \Closure);

                return $this->applyExtenders($abstract, ($binding->concrete)($this, $parameters));
            }

            $object = $binding->concrete instanceof \Closure
                ? $this->callBindingClosure($abstract, $binding->concrete, $parameters)
                : $this->buildGuarded((string) $binding->concrete, $parameters);
        } elseif (\class_exists($abstract)) {
            // 4) Auto-Wiring مستقیم (بدون binding صریح)
            $object = $this->buildGuarded($abstract, $parameters);
        } else {
            throw BindingResolutionException::notFound($abstract);
        }

        $object = $this->applyExtenders($abstract, $object);

        // 5) ذخیره بر اساس Lifetime
        if ($parameters === []) {
            match ($lifetime) {
                Lifetime::Singleton => $this->instances[$abstract] = $object,
                Lifetime::Scoped    => $this->storeScoped($abstract, $object),
                default             => null,
            };
        }

        $this->resolved[$abstract] = true;
        $this->fireResolved($abstract, $object);

        return $object;
    }

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    public function bound(string $abstract): bool
    {
        $abstract = $this->resolveAlias($abstract);

        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract])
            || \class_exists($abstract);
    }

    public function resolved(string $abstract): bool
    {
        return isset($this->resolved[$this->resolveAlias($abstract)]);
    }

    /**
     * Method Injection روی callable/method/static/invokable/`Class@method`.
     */
    public function call(callable|array|string $callback, array $parameters = []): mixed
    {
        [$reflection, $target] = $this->resolveCallable($callback);

        $args = $this->resolveCallParameters($reflection, $parameters, $this->callableName($callback));

        if ($reflection instanceof \ReflectionFunction) {
            return $reflection->invokeArgs($args);
        }

        // resolveCallable فقط ReflectionFunction/ReflectionMethod تولید می‌کند
        /** @var \ReflectionMethod $reflection */
        return $reflection->invokeArgs($target, $args);
    }

    // ─── Scope ────────────────────────────────────────────────────────────

    public function beginScope(string $id): void
    {
        $this->scopeManager->begin($id);
    }

    public function endScope(string $id): void
    {
        $this->scopeManager->end($id);
    }

    public function currentScope(): ?string
    {
        return $this->scopeManager->current();
    }

    /** پاک‌کردن سرویس‌های Scoped (پایان درخواست در Resident — AC-CON-04). */
    public function flushScoped(): void
    {
        $this->scopeManager->flushAll();
    }

    // ─── Lazy ─────────────────────────────────────────────────────────────

    /** Proxy تنبل (بدون eval — ADR-0016): اولین فراخوانی متد → resolve. */
    public function lazy(string $abstract): LazyProxy
    {
        return new LazyProxy($abstract, $this);
    }

    // ─── پاک‌سازی ─────────────────────────────────────────────────────────

    public function forgetInstance(string $abstract): void
    {
        unset($this->instances[$this->resolveAlias($abstract)], $this->resolved[$abstract]);
    }

    public function forgetExtenders(string $abstract): void
    {
        unset($this->extenders[$this->resolveAlias($abstract)]);
    }

    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->tags = [];
        $this->contextual = [];
        $this->extenders = [];
        $this->resolved = [];
        $this->buildStack = [];
        $this->scopeManager->flushAll();
    }

    // ─── Introspection ────────────────────────────────────────────────────

    /** @return array<string, Binding> */
    public function bindings(): array
    {
        return $this->bindings;
    }

    public function reflectionCache(): ReflectionCache
    {
        return $this->reflectionCache;
    }

    public function stats(): ContainerStats
    {
        return new ContainerStats($this->makeCalls, $this->builds, $this->singletonHits, $this->scopedHits, \count($this->bindings));
    }

    /**
     * گراف وابستگی (خروجی برای `tvn container:graph`).
     *
     * @return array<string, list<string>>
     */
    public function dependencyGraph(): array
    {
        $graph = [];
        foreach (\array_keys($this->bindings) as $abstract) {
            $concrete = $this->bindings[$abstract]->concrete;
            if (!\is_string($concrete) || !\class_exists($concrete)) {
                continue;
            }
            $deps = [];
            foreach ($this->reflectionCache->signatureOf($concrete)->parameters as $p) {
                if ($p->isClass && $p->typeString !== null) {
                    $deps[] = $p->typeString;
                }
            }
            $graph[$abstract] = $deps;
        }

        return $graph;
    }

    // ─── ساخت (Auto-Wiring) ───────────────────────────────────────────────

    /**
     * @param class-string          $concrete
     * @param array<string, mixed>  $parameters
     */
    protected function buildGuarded(string $concrete, array $parameters): object
    {
        // تشخیص چرخه (DOC-031 §5.4)
        if (isset($this->buildStack[$concrete])) {
            $path = [...\array_keys($this->buildStack), $concrete];
            $start = (int) \array_search($concrete, $path, true);
            throw CircularDependencyException::detected(\array_slice($path, $start));
        }
        if (\count($this->buildStack) >= self::MAX_DEPTH) {
            throw CircularDependencyException::detected([...$concrete, \array_keys($this->buildStack)]);
        }

        $this->buildStack[$concrete] = \count($this->buildStack);

        try {
            return $this->build($concrete, $parameters);
        } finally {
            unset($this->buildStack[$concrete]);
        }
    }

    /**
     * @param class-string         $concrete
     * @param array<string, mixed> $parameters
     */
    protected function build(string $concrete, array $parameters): object
    {
        $signature = $this->reflectionCache->signatureOf($concrete);

        if (!$signature->isInstantiable) {
            throw BindingResolutionException::notInstantiable($concrete);
        }

        if (!$signature->hasConstructor) {
            $this->builds++;

            return $this->reflectionCache->reflectionOf($concrete)->newInstanceArgs([]);
        }

        $args = [];
        foreach ($signature->parameters as $param) {
            // Variadic بدون override → بقیهٔ آرگومان‌ها حذف می‌شوند (T-11)
            if ($param->isVariadic && !\array_key_exists($param->name, $parameters)) {
                break;
            }
            $value = $this->resolveParameter($concrete, $param, $parameters);
            if ($param->isVariadic && \is_array($value) && \array_key_exists($param->name, $parameters)) {
                foreach ($value as $item) {
                    $args[] = $item;
                }
                continue;
            }
            $args[] = $value;
        }

        $this->builds++;

        return $this->reflectionCache->reflectionOf($concrete)->newInstanceArgs($args);
    }

    /**
     * @param class-string         $concrete
     * @param array<string, mixed> $parameters
     */
    private function resolveParameter(string $concrete, ParameterSignature $param, array $parameters): mixed
    {
        // 1) override صریح (نام پارامتر یا ایندکس عددی)
        if (\array_key_exists($param->name, $parameters)) {
            return $parameters[$param->name];
        }

        // 2) Contextual Binding
        $ctx = $this->contextual[$concrete][$param->typeString ?? '']
            ?? $this->contextual[$concrete]['$' . $param->name]
            ?? null;
        if ($ctx !== null) {
            return $ctx instanceof \Closure ? $ctx($this) : (\is_string($ctx) ? $this->make($ctx) : $ctx);
        }

        // 3) تزریق نوع کلاسی
        if ($param->isClass && $param->typeString !== null) {
            try {
                return $this->make($param->typeString);
            } catch (BindingResolutionException $e) {
                if ($param->hasDefault) {
                    return $param->defaultValue();
                }
                if ($param->nullable) {
                    return null;
                }
                throw $e;
            }
        }

        // 4) Union / DNF (قطعیت: class > interface > builtin > null)
        if ($param->isUnion) {
            $resolved = $this->resolveUnion($concrete, $param, $parameters);
            if ($resolved !== self::UNRESOLVED) {
                return $resolved;
            }
        }

        // 4b) Intersection ساده (A&B)
        if ($param->isIntersectionTyped) {
            $resolved = $this->resolveIntersection($concrete, $param);
            if ($resolved !== self::UNRESOLVED) {
                return $resolved;
            }
        }

        // 5) بدون type
        if (!$param->isClass && !$param->isBuiltin && !$param->isUnion && !$param->isIntersectionTyped && $param->typeString === null) {
            if (!$this->allowUntyped()) {
                throw UntypedParameterException::for($concrete, $param->name);
            }
        }

        // 6) Default / Nullable / Variadic
        if ($param->hasDefault) {
            return $param->defaultValue();
        }
        if ($param->nullable) {
            return null;
        }
        if ($param->isVariadic) {
            return [];   // بدون آرگومان اضافه
        }

        throw BindingResolutionException::unresolvableParameter($concrete, $param->name);
    }

    /**
     * @param class-string         $concrete
     * @param array<string, mixed> $parameters
     */
    private function resolveUnion(string $concrete, ParameterSignature $param, array $parameters): mixed
    {
        $classes = [];
        $interfaces = [];
        $hasNull = false;

        foreach ($param->unionParts as $part) {
            $kind = $part['kind'];
            foreach ($part['names'] as $name) {
                match ($kind) {
                    'class'     => $classes[] = $name,
                    'interface' => $interfaces[] = $name,
                    'builtin'   => $hasNull = $hasNull || $name === 'null',
                    default     => $hasNull = $hasNull || $name === 'null',
                };
                if ($kind === 'builtin' && $name === 'null') {
                    $hasNull = true;
                }
            }
        }

        \sort($classes);         // 🔒 قطعیت: ترتیب الفبایی
        \sort($interfaces);

        $candidates = [];
        foreach ([...$classes, ...$interfaces] as $name) {
            if (isset($this->bindings[$name]) || isset($this->instances[$name]) || \class_exists($name)) {
                $candidates[] = $name;
            }
        }

        if (\count($candidates) === 1) {
            try {
                return $this->make($candidates[0]);
            } catch (BindingResolutionException) {
                // fall through
            }
        }

        if (\count($candidates) > 1) {
            if (\array_key_exists($param->name, $parameters)) {
                return $parameters[$param->name];
            }
            throw AmbiguousUnionException::for($concrete, $param->name, $candidates);
        }

        // interfaceهای bound نشده: بررسی contextual/alias
        foreach ($interfaces as $interface) {
            if ($this->bound($interface)) {
                try {
                    return $this->make($interface);
                } catch (BindingResolutionException) {
                    continue;
                }
            }
        }

        if ($hasNull) {
            return null;
        }

        return self::UNRESOLVED;
    }

    /**
     * @param class-string $concrete
     */
    private function resolveIntersection(string $concrete, ParameterSignature $param): mixed
    {
        foreach ($param->intersectionNames as $name) {
            if (!\class_exists($name)) {
                continue;
            }
            $candidate = $this->make($name);
            $satisfies = true;
            foreach ($param->intersectionNames as $member) {
                if (!$candidate instanceof $member) {
                    $satisfies = false;
                    break;
                }
            }
            if ($satisfies) {
                return $candidate;
            }
        }

        return self::UNRESOLVED;
    }

    /**
     * @param class-string $abstract
     */
    private function callBindingClosure(string $abstract, \Closure $concrete, array $parameters): object
    {
        $result = $concrete($this, $parameters);

        if (!\is_object($result)) {
            throw BindingResolutionException::notFound($abstract);
        }

        return $result;
    }

    private function applyExtenders(string $abstract, object $instance): object
    {
        foreach ($this->extenders[$abstract] ?? [] as $extender) {
            $instance = $extender($instance, $this);
        }

        return $instance;
    }

    private function storeScoped(string $abstract, object $object): void
    {
        $scope = $this->scopeManager->current();
        if ($scope === null) {
            throw ScopeNotActiveException::for($abstract);
        }
        $this->scopeManager->store($scope, $abstract, $object);
    }

    // ─── Method Injection helpers ─────────────────────────────────────────

    /**
     * @param callable|array{0: object|class-string, 1: string}|string $callback
     *
     * @return array{0: \ReflectionFunctionAbstract, 1: object|null}
     */
    private function resolveCallable(callable|array|string $callback): array
    {
        if ($callback instanceof \Closure) {
            return [new \ReflectionFunction($callback), null];
        }

        if (\is_array($callback)) {
            [$target, $method] = $callback;
            if (\is_string($target)) {
                $target = \class_exists($target) && !(new \ReflectionClass($target))->isInstantiable()
                    ? $target
                    : $this->make($target);
            }

            return [new \ReflectionMethod($target, $method), \is_object($target) ? $target : null];
        }

        if (\is_string($callback)) {
            if (\str_contains($callback, '@')) {
                [$class, $method] = \explode('@', $callback, 2);
                $instance = $this->make($class);

                return [new \ReflectionMethod($instance, $method), $instance];
            }
            if (\str_contains($callback, '::')) {
                [$class, $method] = \explode('::', $callback, 2);

                return [new \ReflectionMethod($class, $method), null];
            }
            if (\function_exists($callback)) {
                return [new \ReflectionFunction($callback), null];
            }
            $instance = $this->make($callback);
            if (\method_exists($instance, '__invoke')) {
                return [new \ReflectionMethod($instance, '__invoke'), $instance];
            }
        }

        if (\is_object($callback)) {
            if ($callback instanceof \Closure) {
                return [new \ReflectionFunction($callback), null];
            }
            if (\method_exists($callback, '__invoke')) {
                return [new \ReflectionMethod($callback, '__invoke'), $callback];
            }
        }

        throw BindingResolutionException::unsafe(\is_string($callback) ? $callback : 'callback');
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<mixed>
     */
    private function resolveCallParameters(\ReflectionFunctionAbstract $reflection, array $parameters, string $name): array
    {
        $args = [];
        foreach ($reflection->getParameters() as $index => $p) {
            if (\array_key_exists($p->getName(), $parameters)) {
                $args[] = $parameters[$p->getName()];
                continue;
            }
            if (\array_key_exists($index, $parameters)) {
                $args[] = $parameters[$index];
                continue;
            }

            $type = $p->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                try {
                    $args[] = $this->make($type->getName());
                    continue;
                } catch (BindingResolutionException $e) {
                    if ($p->isDefaultValueAvailable()) {
                        $args[] = $p->getDefaultValue();
                        continue;
                    }
                    if ($type->allowsNull()) {
                        $args[] = null;
                        continue;
                    }
                    throw $e;
                }
            }

            if ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
                continue;
            }
            if ($p->allowsNull()) {
                $args[] = null;
                continue;
            }
            if ($p->isVariadic()) {
                continue;
            }

            throw BindingResolutionException::unresolvableParameter($name, $p->getName());
        }

        return $args;
    }

    /**
     * @param callable|array|string $callback
     */
    private function callableName(callable|array|string $callback): string
    {
        if (\is_string($callback)) {
            return $callback;
        }
        if (\is_array($callback)) {
            $target = $callback[0];

            return (\is_object($target) ? $target::class : (string) $target) . '::' . $callback[1];
        }

        return 'closure';
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function resolveAlias(string $abstract): string
    {
        $seen = [];
        while (isset($this->aliases[$abstract])) {
            if (isset($seen[$abstract])) {
                break;   // حلقهٔ alias — جلوگیری از loop بی‌نهایت
            }
            $seen[$abstract] = true;
            $abstract = $this->aliases[$abstract];
        }

        return $abstract;
    }

    /**
     * 🔒 امنیت: abstract با طول غیرمتعارف یا بایت null ممنوع (DOC-031 §9).
     */
    private function assertSafeAbstract(string $abstract): void
    {
        if (\str_contains($abstract, "\0") || \strlen($abstract) > 300) {
            throw BindingResolutionException::unsafe($abstract);
        }
    }

    private function allowUntyped(): bool
    {
        return (bool) ($this->config['strict']['allow_untyped_parameters'] ?? false);
    }

    private function fireResolving(string $abstract): void
    {
        $this->fireEvent('container.resolving', ['abstract' => $abstract]);
    }

    private function fireResolved(string $abstract, object $instance): void
    {
        $this->fireEvent('container.resolved', ['abstract' => $abstract, 'class' => $instance::class]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function fireEvent(string $event, array $payload): void
    {
        if ($this->dispatchingEvent || !isset($this->instances['events'])) {
            return;
        }

        $this->dispatchingEvent = true;
        try {
            /** @var \Tavaan\Core\Contracts\Events\DispatcherInterface $dispatcher */
            $dispatcher = $this->instances['events'];
            $dispatcher->dispatch($event, $payload);
        } catch (\Throwable) {
            // رویداد دیباگ نباید resolve را بشکند
        } finally {
            $this->dispatchingEvent = false;
        }
    }
}

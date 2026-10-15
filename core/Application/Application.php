<?php

declare(strict_types=1);

namespace Tavaan\Core\Application;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Container\ContextualBindingBuilder;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Core
 * Application — هستهٔ اپلیکیشن: Container + محیط + Providerها + حالت اجرا.
 *
 * بر پایهٔ «ترکیب» (Composition) به‌جای وراثت از موتور Container — تا core
 * در زمان کامپایل به engines وابسته نباشد. کانتینر پیش‌فرض در ساخت با
 * class-string نمونه‌سازی می‌شود (composition root).
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class Application implements ApplicationInterface
{
    /** کانتینر پیش‌فرض (class-string تا core به engine وابستگی compile-time نداشته باشد) */
    private const DEFAULT_CONTAINER = 'Tavaan\Engine\Container\Container';

    private ContainerInterface $container;

    /** @var list<ServiceProvider> */
    private array $providers = [];

    /** @var array<class-string, true> */
    private array $loadedProviders = [];

    private RuntimeMode $mode = RuntimeMode::Http;

    /** محیط تعیین‌شده توسط LoadEnvironmentVariables (پیش از bind شدن config) */
    private ?string $environmentOverride = null;

    private string $basePath;

    public function __construct(string $basePath, ?ContainerInterface $container = null)
    {
        $this->basePath = \rtrim($basePath, '/\\');

        if ($container === null) {
            $containerClass = self::DEFAULT_CONTAINER;
            if (!\class_exists($containerClass)) {
                throw new \LogicException('Default container engine (engines/container) is not available.');
            }
            /** @var ContainerInterface $container */
            $container = new $containerClass();
        }
        $this->container = $container;

        $this->container->instance('app', $this);
        $this->container->instance(self::class, $this);
        $this->container->instance(ApplicationInterface::class, $this);
        $this->container->instance(ContainerInterface::class, $this->container);

        if (PHP_SAPI === 'cli') {
            $this->mode = RuntimeMode::Console;
        }
    }

    // ─── ApplicationInterface ─────────────────────────────────────────────

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path === '' ? '' : '/' . \ltrim($path, '/'));
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage' . ($path === '' ? '' : '/' . \ltrim($path, '/')));
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config' . ($path === '' ? '' : '/' . \ltrim($path, '/')));
    }

    public function version(): string
    {
        return \defined('TVN_VERSION') ? (string) \TVN_VERSION : 'dev';
    }

    public function runningInConsole(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }

    public function runningUnitTests(): bool
    {
        return $this->mode === RuntimeMode::Testing || $this->environment() === 'testing';
    }

    public function environment(): string
    {
        if ($this->environmentOverride !== null) {
            return $this->environmentOverride;
        }

        if ($this->container->bound('config')) {
            $env = $this->container->make('config')->get('app.env');
            if (\is_string($env) && $env !== '') {
                return $env;
            }
        }

        return (string) ($_SERVER['TVN_APP_ENV'] ?? $_ENV['TVN_APP_ENV'] ?? 'production');
    }

    /** تعیین محیط از .env (توسط Bootstrapper — پیش از بارگذاری config). */
    public function setEnvironment(string $environment): void
    {
        $this->environmentOverride = $environment;
    }

    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    public function isLocal(): bool
    {
        return \in_array($this->environment(), ['local', 'development'], true);
    }

    public function mode(): RuntimeMode
    {
        return $this->mode;
    }

    public function setMode(RuntimeMode $mode): void
    {
        $this->mode = $mode;
    }

    public function bootTimeMs(): float
    {
        $start = \defined('TVN_START') ? (float) \TVN_START : \microtime(true);

        return (\microtime(true) - $start) * 1000;
    }

    // ─── Providerها ───────────────────────────────────────────────────────

    public function registerProvider(ServiceProvider $provider): ServiceProvider
    {
        $class = $provider::class;
        if (isset($this->loadedProviders[$class])) {
            return $provider;
        }

        $this->providers[] = $provider;
        $this->loadedProviders[$class] = true;
        $provider->register();

        return $provider;
    }

    /** @return list<ServiceProvider> */
    public function providers(): array
    {
        return $this->providers;
    }

    public function terminate(): void
    {
        foreach ($this->providers as $provider) {
            try {
                $provider->shutdown();
            } catch (\Throwable) {
                // shutdown نباید خطا دهد
            }
        }
    }

    /** کانتینر داخلی (برای Bootstrapperها). */
    public function container(): ContainerInterface
    {
        return $this->container;
    }

    // ─── ContainerInterface (delegate) ────────────────────────────────────

    public function bind(string $abstract, \Closure|string|null $concrete = null, bool $shared = false): void
    {
        $this->container->bind($abstract, $concrete, $shared);
    }

    public function singleton(string $abstract, \Closure|string|null $concrete = null): void
    {
        $this->container->singleton($abstract, $concrete);
    }

    public function scoped(string $abstract, \Closure|string|null $concrete = null): void
    {
        $this->container->scoped($abstract, $concrete);
    }

    public function factory(string $abstract, \Closure $factory): void
    {
        $this->container->factory($abstract, $factory);
    }

    public function instance(string $abstract, object $instance): object
    {
        return $this->container->instance($abstract, $instance);
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->container->alias($abstract, $alias);
    }

    public function tag(array|string $abstracts, string ...$tags): void
    {
        $this->container->tag($abstracts, ...$tags);
    }

    public function tagged(string $tag): iterable
    {
        return $this->container->tagged($tag);
    }

    public function bound(string $abstract): bool
    {
        return $this->container->bound($abstract);
    }

    public function resolved(string $abstract): bool
    {
        return $this->container->resolved($abstract);
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->container->make($abstract, $parameters);
    }

    public function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    public function call(callable|array|string $callback, array $parameters = []): mixed
    {
        return $this->container->call($callback, $parameters);
    }

    public function when(string $consumer): ContextualBindingBuilder
    {
        return $this->container->when($consumer);
    }

    public function extend(string $abstract, \Closure $extender): void
    {
        $this->container->extend($abstract, $extender);
    }

    public function flush(): void
    {
        $this->container->flush();
    }

    /**
     * پاس‌شدن متدهای اضافی کانتینر (beginScope/stats/lazy/…).
     *
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->container->{$method}(...$arguments);
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Core\Application;

use Tavaan\Core\Bootstrap\BootProviders;
use Tavaan\Core\Bootstrap\BootstrapperInterface;
use Tavaan\Core\Bootstrap\DiscoverModules;
use Tavaan\Core\Bootstrap\HandleExceptions;
use Tavaan\Core\Bootstrap\LoadConfiguration;
use Tavaan\Core\Bootstrap\LoadEnvironmentVariables;
use Tavaan\Core\Bootstrap\RegisterFacades;
use Tavaan\Core\Bootstrap\RegisterProviders;
use Tavaan\Core\Bootstrap\SetLocaleAndTimezone;

/**
 * تَوان | Tavaan — Core
 * ApplicationBuilder — Fluent API ساخت و بوت اپلیکیشن (DOC-027 §7).
 *
 * ### مثال
 * ```php
 * $app = ApplicationBuilder::create(TVN_BASE_PATH)->forConsole()->boot();
 * ```
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class ApplicationBuilder
{
    /** @var list<class-string<BootstrapperInterface>> */
    private array $bootstrappers = [];

    /** @var list<class-string<BootstrapperInterface>> */
    private array $withoutBootstrappers = [];

    /** @var list<class-string>|null */
    private ?array $providers = null;

    private ?string $envFile = null;

    private RuntimeMode $mode = RuntimeMode::Http;

    private function __construct(private readonly string $basePath)
    {
        $this->bootstrappers = self::defaultBootstrappers();
    }

    public static function create(string $basePath): self
    {
        return new self($basePath);
    }

    /** @return list<class-string<BootstrapperInterface>> */
    public static function defaultBootstrappers(): array
    {
        return [
            LoadEnvironmentVariables::class,
            LoadConfiguration::class,
            HandleExceptions::class,
            RegisterFacades::class,
            RegisterProviders::class,
            DiscoverModules::class,
            BootProviders::class,
            SetLocaleAndTimezone::class,
        ];
    }

    public function withEnvFile(string $file): self
    {
        $this->envFile = $file;

        return $this;
    }

    /** @param list<class-string> $providers */
    public function withProviders(array $providers): self
    {
        $this->providers = $providers;

        return $this;
    }

    /** @param class-string<BootstrapperInterface> $class */
    public function withBootstrapper(string $class): self
    {
        $this->bootstrappers[] = $class;

        return $this;
    }

    /** @param class-string<BootstrapperInterface> $class */
    public function withoutBootstrapper(string $class): self
    {
        $this->withoutBootstrappers[] = $class;

        return $this;
    }

    public function forConsole(): self
    {
        $this->mode = RuntimeMode::Console;

        return $this;
    }

    public function forTesting(): self
    {
        $this->mode = RuntimeMode::Testing;

        return $this;
    }

    public function forQueue(): self
    {
        $this->mode = RuntimeMode::Queue;

        return $this;
    }

    public function boot(): Application
    {
        if ($this->envFile !== null) {
            $_SERVER['TVN_ENV_FILE'] = $this->envFile;
        }

        $app = new Application($this->basePath);
        $app->setMode($this->mode);

        if ($this->providers !== null && $app->bound('config')) {
            $app->make('config')->set('providers', $this->providers);
        }

        foreach ($this->resolveBootstrappers() as $bootstrapperClass) {
            /** @var BootstrapperInterface $bootstrapper */
            $bootstrapper = $app->make($bootstrapperClass);
            $bootstrapper->bootstrap($app);
        }

        // Providerهای override شده پس از بوت config اعمال شوند
        if ($this->providers !== null) {
            foreach ($this->providers as $providerClass) {
                if (\class_exists($providerClass)) {
                    $app->registerProvider($app->make($providerClass));
                }
            }
        }

        return $app;
    }

    /** @return list<class-string<BootstrapperInterface>> */
    private function resolveBootstrappers(): array
    {
        return \array_values(\array_diff($this->bootstrappers, $this->withoutBootstrappers));
    }
}

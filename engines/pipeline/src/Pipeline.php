<?php

declare(strict_types=1);

namespace Tavaan\Engine\Pipeline;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Pipeline\PipelineInterface;
use Tavaan\Engine\Pipeline\Exceptions\PipelineMaxDepthException;
use Tavaan\Engine\Pipeline\Exceptions\PipelinePipeNotFoundException;

/**
 * تَوان | Tavaan — Pipeline
 * Pipeline — عبور Passable دلخواه از زنجیره‌ای از پردازشگرها (الگوی Onion).
 *
 * پایهٔ Middleware در HTTP/Queue/Console و Validation Chain.
 * بدون Closure::bind (سبک)، با پشتیبانی پارامتر (`auth:web,admin`)،
 * alias از config، `after()`/`terminate()` عمومی و max_depth.
 *
 * ### مثال
 * ```php
 * $out = (new Pipeline($container))
 *     ->send($request)
 *     ->through([TrustProxies::class, 'auth:web,admin', fn ($p, $next) => $next($p)])
 *     ->then(fn ($r) => $dispatcher->dispatch($r));
 * ```
 *
 * @api 1.0
 * @since 1.0.0
 * @see docs/03-engines/pipeline.md
 */
final class Pipeline implements PipelineInterface
{
    private mixed $passable = null;

    /** @var list<string|callable|object> */
    private array $pipes = [];

    private string $method = 'handle';

    /** @var list<callable> مراحل پس از پایان (terminate) */
    private array $terminateStages = [];

    /** @var list<object> pipeهای resolve شده (برای terminate) */
    private array $resolvedPipes = [];

    /** @var array<string, class-string> alias → class */
    private array $aliases = [];

    private int $maxDepth = 64;

    public function __construct(private readonly ?ContainerInterface $container = null, array $config = [])
    {
        $this->aliases = (array) ($config['aliases'] ?? []);
        $this->maxDepth = (int) ($config['max_depth'] ?? 64);
    }

    public function send(mixed $passable): static
    {
        $this->passable = $passable;

        return $this;
    }

    public function through(iterable $pipes): static
    {
        $this->pipes = [];
        foreach ($pipes as $pipe) {
            $this->pipe($pipe);
        }

        return $this;
    }

    public function pipe(string|callable|object $pipe): static
    {
        $this->pipes[] = $pipe;
        if (\count($this->pipes) > $this->maxDepth) {
            throw PipelineMaxDepthException::for($this->maxDepth);
        }

        return $this;
    }

    public function via(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    /** افزودن مرحلهٔ پس از پایان (با پاسable و نتیجه). */
    public function after(callable $stage): static
    {
        $this->terminateStages[] = $stage;

        return $this;
    }

    /**
     * @param list<string> $aliases
     */
    public function withAliases(array $aliases): static
    {
        $this->aliases = [...$this->aliases, ...$aliases];

        return $this;
    }

    public function then(callable $destination): mixed
    {
        $pipeline = \array_reduce(
            \array_reverse($this->pipes),
            $this->carry(),
            static fn (mixed $passable): mixed => $destination($passable),
        );

        return $pipeline($this->passable);
    }

    public function thenReturn(): mixed
    {
        return $this->then(static fn (mixed $passable): mixed => $passable);
    }

    /** اجرای مراحل پس از پایان — حتی با Exception (در Kernel با try/finally). */
    public function terminate(mixed $passable, mixed $result): void
    {
        foreach ($this->terminateStages as $stage) {
            $stage($passable, $result);
        }

        foreach ($this->resolvedPipes as $pipe) {
            if (\method_exists($pipe, 'terminate')) {
                $pipe->terminate($passable, $result);
            }
        }
    }

    private function carry(): \Closure
    {
        return function (\Closure $next, string|callable|object $pipe): \Closure {
            return function (mixed $passable) use ($next, $pipe): mixed {
                if (\is_callable($pipe) && !\is_string($pipe)) {
                    return $pipe($passable, $next);
                }

                [$name, $parameters] = $this->parsePipeString($pipe);

                if (\is_object($name)) {
                    $this->resolvedPipes[] = $name;

                    return $name->{$this->method}($passable, $next, ...$parameters);
                }

                $instance = $this->resolvePipe((string) $name);
                $this->resolvedPipes[] = $instance;

                return $instance->{$this->method}($passable, $next, ...$parameters);
            };
        };
    }

    /**
     * `'auth:web,admin'` → `['auth', ['web','admin']]`.
     *
     * @return array{0: string|object, 1: list<string>}
     */
    private function parsePipeString(string|object $pipe): array
    {
        if (!\is_string($pipe)) {
            return [$pipe, []];
        }

        [$name, $parameters] = \array_pad(\explode(':', $pipe, 2), 2, '');

        if (isset($this->aliases[$name])) {
            $name = $this->aliases[$name];
        }

        return [$name, $parameters === '' ? [] : \explode(',', $parameters)];
    }

    private function resolvePipe(string $name): object
    {
        if ($this->container !== null && ($this->container->bound($name) || \class_exists($name))) {
            $instance = $this->container->make($name);
            if (\is_object($instance)) {
                return $instance;
            }
        }

        throw PipelinePipeNotFoundException::for($name);
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http;

use Tavaan\Core\Context\RequestStack;
use Tavaan\Core\Context\TraceContext;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Http\KernelInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Core\Contracts\Http\TerminableMiddleware;
use Tavaan\Engine\Error\ExceptionHandler;
use Tavaan\Engine\Http\Routing\ControllerResolver;
use Tavaan\Engine\Http\Routing\Route;
use Tavaan\Engine\Http\Routing\Router;
use Tavaan\Engine\Log\Logger;
use Tavaan\Engine\Pipeline\Pipeline;

/**
 * تَوان | Tavaan — Http
 * HttpKernel — orchestrator چرخهٔ درخواست (DOC-025 P7..P14).
 *
 * جریان: capture → scope('request') → global middleware (Pipeline) →
 * route match → route middleware → controller/action → normalize →
 * ResponsePreparer (ETag/compression) → send → terminate.
 *
 * 🔒 در پایان هر درخواست: flushScoped (Resident safety) + TraceContext reset.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class HttpKernel implements KernelInterface
{
    /** @var list<object> middlewareهای resolve‌شده (برای terminate) */
    private array $resolvedMiddleware = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Router $router,
        private readonly RequestStack $requestStack,
        private readonly ExceptionHandler $exceptions,
        private readonly ResponsePreparer $preparer,
        private readonly ControllerResolver $controller,
        private readonly array $config = [],
        private readonly ?DispatcherInterface $events = null,
    ) {
    }

    /**
     * فهرست middlewareهای resolve‌شدهٔ درخواست جاری (برای Debug Bar — DOC-067 §5).
     *
     * @return list<object>
     */
    public function resolvedMiddleware(): array
    {
        return $this->resolvedMiddleware;
    }

    public function handle(RequestInterface $request): ResponseInterface
    {
        if (!$request instanceof Request) {
            throw new \InvalidArgumentException('HttpKernel requires Tavaan\Engine\Http\Request.');
        }

        $this->container->beginScope('request');
        $this->container->instance('request', $request);
        $this->container->instance(Request::class, $request);
        $this->container->instance(RequestInterface::class, $request);
        $this->requestStack->push($request);

        if ($this->container->bound('url')) {
            $this->container->make('url')->setRequestContext($request);
        }

        $this->events?->dispatch('http.request.received', [
            'method' => $request->method(),
            'path' => $request->path(),
            'trace_id' => TraceContext::traceId(),
        ]);

        try {
            $pipeline = new Pipeline($this->container, ['aliases' => (array) ($this->config['middleware']['aliases'] ?? []), 'max_depth' => 64]);

            /** @var Response $response */
            $response = $pipeline
                ->send($request)
                ->through($this->globalMiddleware())
                ->then(fn (Request $r): Response => $this->dispatchToRouter($r));

            $response = $this->preparer->prepare($response, $request);

            $this->events?->dispatch('http.request.handled', ['status' => $response->statusCode()]);

            return $response;
        } catch (\Throwable $e) {
            return $this->renderException($e, $request);
        }
    }

    public function terminate(RequestInterface $request, ResponseInterface $response): void
    {
        try {
            // middlewareها با terminate — ترتیب معکوس (DOC-025 P14)
            foreach (\array_reverse($this->resolvedMiddleware) as $middleware) {
                if ($middleware instanceof TerminableMiddleware) {
                    $middleware->terminate($request, $response);
                }
            }

            $this->events?->dispatch('http.request.terminated', [
                'status' => $response->statusCode(),
                'duration_ms' => $request instanceof Request ? $request->elapsedMs() : 0.0,
            ]);
        } finally {
            $this->resolvedMiddleware = [];
            $this->requestStack->pop();
            $this->container->flushScoped();     // 🔒 Resident safety
            Logger::resetRequestCounter();
            TraceContext::reset();
        }
    }

    /** send → terminate → پایان (الگوی DOC-039 §4.2). */
    public function sendAndTerminate(Request $request, Response $response): void
    {
        $response->send();

        if (\function_exists('fastcgi_finish_request')) {
            \fastcgi_finish_request();
        }

        $this->terminate($request, $response);
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function dispatchToRouter(Request $request): Response
    {
        $match = $this->router->matchRequest($request);
        $route = $match->route;

        $request = $request->withRoute($route, $match->allParameters());
        $this->container->instance('request', $request);

        $this->events?->dispatch('http.route.matched', [
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $this->describeAction($route),
        ]);

        // route middleware pipeline
        $middleware = $this->resolveRouteMiddleware($route);
        $pipeline = new Pipeline($this->container, ['aliases' => (array) ($this->config['middleware']['aliases'] ?? []), 'max_depth' => 64]);

        return $pipeline
            ->send($request)
            ->through($middleware)
            ->then(function (Request $r) use ($match, $route): Response {
                // binders صریح روتر
                $params = $match->allParameters();
                foreach ($this->router->binders() as $name => $resolver) {
                    if (\array_key_exists($name, $params)) {
                        $params[$name] = $resolver((string) $params[$name]);
                    }
                }

                return $this->controller->dispatch($r, $route, $params);
            });
    }

    /** @return list<string> */
    private function globalMiddleware(): array
    {
        return (array) ($this->config['middleware']['global'] ?? []);
    }

    /** @return list<string> */
    private function resolveRouteMiddleware(Route $route): array
    {
        $groups = (array) ($this->config['middleware']['groups'] ?? []);
        $resolved = [];

        foreach ($route->getMiddleware() as $entry) {
            if (isset($groups[$entry])) {
                foreach ((array) $groups[$entry] as $groupMember) {
                    $resolved[] = (string) $groupMember;
                }
                continue;
            }
            $resolved[] = $entry;
        }

        return $resolved;
    }

    private function renderException(\Throwable $e, Request $request): Response
    {
        $mode = $request->expectsJson() ? 'json' : ($this->container->bound('config') && (bool) $this->container->make('config')->get('app.debug', false) && !$request instanceof \stdClass ? 'auto' : 'auto');

        $result = $this->exceptions->handle($e, $mode);

        $response = new Response($result->body, $result->status, ['Content-Type' => $result->contentType, ...$result->headers]);

        return $this->preparer->prepare($response, $request);
    }

    private function describeAction(Route $route): string
    {
        $action = $route->action();
        if ($action instanceof \Closure) {
            return 'Closure';
        }
        if (\is_array($action)) {
            return (\is_object($action[0]) ? $action[0]::class : (string) $action[0]) . '@' . $action[1];
        }

        return (string) $action;
    }
}

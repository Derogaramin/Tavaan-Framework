<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Routing
 * ControllerResolver — resolve اکشن روت و فراخوانی با Method Injection.
 *
 * پارامترها: route params (با cast نوع) → Request → Container.
 * خروجی اکشن به Response نرمال‌سازی می‌شود (DOC-025 P12).
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class ControllerResolver
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function dispatch(Request $request, Route $route, array $routeParameters = []): Response
    {
        $action = $route->action();

        // binders صریح
        foreach ($routeParameters as $name => $value) {
            $routeParameters[$name] = $value;
        }

        $callback = $this->resolveCallable($action);
        $parameters = $this->resolveParameters($callback, $request, $routeParameters);

        $result = $callback(...$parameters);

        return $this->normalizeResult($result);
    }

    /**
     * @param \Closure|class-string|array{0: class-string|object, 1: string} $action
     *
     * @return \Closure(...): mixed
     */
    private function resolveCallable(mixed $action): \Closure
    {
        if ($action instanceof \Closure) {
            return $action;
        }

        if (\is_string($action)) {
            if (\str_contains($action, '@')) {
                [$class, $method] = \explode('@', $action, 2);
                $instance = $this->container->make($class);

                return $instance->{$method}(...);
            }
            if (\str_contains($action, '::')) {
                [$class, $method] = \explode('::', $action, 2);

                return [$class, $method](...) ;
            }
            // invokable class
            $instance = $this->container->make($action);

            return $instance(...);
        }

        if (\is_array($action)) {
            [$target, $method] = $action;
            $instance = \is_object($target) ? $target : $this->container->make($target);

            return $instance->{$method}(...);
        }

        throw new \InvalidArgumentException('Unsupported route action type: ' . \get_debug_type($action));
    }

    /**
     * @return list<mixed> پارامترهای آمادهٔ فراخوانی
     */
    private function resolveParameters(\Closure $callback, Request $request, array $routeParameters): array
    {
        $reflection = new \ReflectionFunction($callback);
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();

            // 1) route parameter (با cast)
            if (\array_key_exists($name, $routeParameters)) {
                $args[] = $this->castParameter($routeParameters[$name], $param);
                continue;
            }

            $type = $param->getType();

            // 2) Request
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                if ($typeName === Request::class || $typeName === \Tavaan\Core\Contracts\Http\RequestInterface::class) {
                    $args[] = $request;
                    continue;
                }

                // FormRequest (موتور validation — DOC-051 §5): authorize + validate خودکار
                if (\class_exists(\Tavaan\Engine\Validation\FormRequest::class)
                    && \is_subclass_of($typeName, \Tavaan\Engine\Validation\FormRequest::class)) {
                    /** @var \Tavaan\Engine\Validation\FormRequest $formRequest */
                    $formRequest = $typeName::createFrom($request);
                    $formRequest->validateResolved();
                    $args[] = $formRequest;
                    continue;
                }

                // Enum backed
                if (\enum_exists($typeName) && \array_key_exists($name, $routeParameters)) {
                    $args[] = $typeName::from($routeParameters[$name]);
                    continue;
                }

                // کلاس‌های قابل resolve از container
                try {
                    $args[] = $this->container->make($typeName);
                    continue;
                } catch (\Throwable) {
                    if ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                        continue;
                    }
                    if ($type->allowsNull()) {
                        $args[] = null;
                        continue;
                    }
                    throw new \Tavaan\Core\Exceptions\Http\RouteNotFoundException(
                        messageId: 'http.route.not_found',
                        context: ['parameter' => $name],
                    );
                }
            }

            // 3) default / nullable / variadic
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }
            if ($param->allowsNull()) {
                $args[] = null;
                continue;
            }
            if ($param->isVariadic()) {
                continue;
            }

            // 4) builtin بدون route param → از query/body
            $fromInput = $request->input($name);
            if ($fromInput !== null) {
                $args[] = $this->castValue($fromInput, $type);
                continue;
            }

            throw \Tavaan\Engine\Http\Routing\Exceptions\UrlGenerationException::missingParameters('action', [$name]);
        }

        return $args;
    }

    private function castParameter(mixed $value, \ReflectionParameter $param): mixed
    {
        $type = $param->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        // Enum backed از route param
        if (!$type->isBuiltin() && \enum_exists($type->getName())) {
            $enumClass = $type->getName();

            return \is_string($value) || \is_int($value) ? $enumClass::from($value) : $value;
        }

        return $this->castValue($value, $type);
    }

    private function castValue(mixed $value, ?\ReflectionType $type): mixed
    {
        if (!$type instanceof \ReflectionNamedType || !$type->isBuiltin() || !\is_string($value)) {
            return $value;
        }

        return match ($type->getName()) {
            'int'   => \is_numeric($value) ? (int) $value : $value,
            'float' => \is_numeric($value) ? (float) $value : $value,
            'bool'  => \filter_var($value, FILTER_VALIDATE_BOOL),
            default => $value,
        };
    }

    /**
     * نرمال‌سازی خروجی اکشن → Response (DOC-025 P12).
     */
    public function normalizeResult(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result instanceof \Tavaan\Core\Contracts\Http\ResponseInterface) {
            return new Response($result->body(), $result->statusCode());
        }

        if ($result === null) {
            return new Response('', 204);
        }

        if (\is_string($result)) {
            return new Response($result, 200);
        }

        if (\is_int($result) || \is_float($result)) {
            return new Response((string) $result, 200);
        }

        if (\is_array($result) || $result instanceof \JsonSerializable) {
            return Response::json($result);
        }

        if (\is_object($result) && \method_exists($result, 'render')) {
            // View engine
            $rendered = $result->render();
            if (\is_string($rendered)) {
                return new Response($rendered, 200);
            }
        }

        if (\is_object($result) && \method_exists($result, 'toArray')) {
            return Response::json($result->toArray());
        }

        if (\is_bool($result)) {
            return Response::json(['result' => $result]);
        }

        return new Response((string) $result, 200);
    }
}

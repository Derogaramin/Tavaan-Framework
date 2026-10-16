<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\OpenApi;

use Tavaan\Engine\Api\Attributes\Api;
use Tavaan\Engine\Api\Attributes\ApiParam;
use Tavaan\Engine\Api\Attributes\ApiResponse;
use Tavaan\Engine\Http\Routing\Route;
use Tavaan\Engine\Http\Routing\Router;
use Tavaan\Engine\Validation\FormRequest;

/**
 * تَوان | Tavaan — Api
 * OpenApiGenerator — تولید سند OpenAPI 3.1 از Route + Attribute + FormRequest (DOC-044 §4).
 *
 * ```bash
 * tvn api:docs --version=v1 --format=yaml --output=docs/openapi.yaml
 * ```
 *
 * منابع متادیتا:
 * - #[Api(tags, summary, description, operationId, deprecated)]
 * - #[ApiResponse(status, description, resource)] (تکرارپذیر)
 * - #[ApiParam(name, in, type, required, description, format)] (تکرارپذیر)
 * - FormRequest::rules() پارامتر متد controller → requestBody schema
 * - route wheres (whereNumber/whereUuid/…) → نوع پارامتر مسیر
 * - middleware 'auth' → security bearerAuth
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
final class OpenApiGenerator
{
    /** @param array<string, mixed> $config کلید api در config */
    public function __construct(
        private readonly Router $router,
        private readonly array $config = [],
    ) {
    }

    /**
     * تولید سند OpenAPI برای یک نگارش.
     *
     * @return array<string, mixed>
     */
    public function generate(string $version): array
    {
        $version = $this->normalizeVersion($version);
        $openapi = (array) ($this->config['openapi'] ?? []);
        $prefix = \trim((string) ($this->config['prefix'] ?? 'api'), '/');

        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => (string) ($openapi['title'] ?? 'Tavaan API'),
                'version' => (string) ($openapi['version'] ?? '1.0.0'),
                ...(isset($openapi['description']) ? ['description' => (string) $openapi['description']] : []),
            ],
            'servers' => $openapi['servers'] ?? [],
            'paths' => [],
        ];

        $usesAuth = false;

        foreach ($this->routesForVersion($version, $prefix) as $route) {
            $path = $this->convertPath($route->uri());
            $reflection = $this->reflectAction($route->action());
            $apiAttr = $reflection !== null ? $this->firstAttribute($reflection, Api::class) : null;

            /** @var list<ApiResponse> $responseAttrs */
            $responseAttrs = $reflection !== null ? $this->attributes($reflection, ApiResponse::class) : [];
            /** @var list<ApiParam> $paramAttrs */
            $paramAttrs = $reflection !== null ? $this->attributes($reflection, ApiParam::class) : [];

            $methods = \array_values(\array_filter(
                $route->methods(),
                static fn (string $m): bool => !\in_array($m, ['HEAD', 'OPTIONS'], true),
            ));
            if ($methods === []) {
                continue;
            }

            $tags = $apiAttr?->tags !== null && $apiAttr->tags !== []
                ? $apiAttr->tags
                : [$this->deriveTag($route, $prefix, $version)];

            $hasAuth = $this->routeUsesAuth($route);
            $usesAuth = $usesAuth || $hasAuth;

            foreach ($methods as $method) {
                $operation = [
                    'operationId' => $apiAttr?->operationId !== null && $apiAttr->operationId !== ''
                        ? $apiAttr->operationId
                        : ($route->getName() ?: $this->deriveOperationId($method, $path)),
                    'tags' => $tags,
                ];
                if ($apiAttr !== null && $apiAttr->summary !== '') {
                    $operation['summary'] = $apiAttr->summary;
                }
                if ($apiAttr !== null && $apiAttr->description !== '') {
                    $operation['description'] = $apiAttr->description;
                }
                if ($apiAttr?->deprecated === true) {
                    $operation['deprecated'] = true;
                }

                $parameters = $this->pathParameters($route, $paramAttrs);
                if ($parameters !== []) {
                    $operation['parameters'] = $parameters;
                }

                $body = $this->requestBody($reflection, \mb_strtolower($method));
                if ($body !== null) {
                    $operation['requestBody'] = $body;
                }

                $operation['responses'] = $this->responses($responseAttrs);

                if ($hasAuth) {
                    $operation['security'] = [['bearerAuth' => []]];
                }

                $spec['paths'][$path][\mb_strtolower($method)] = $operation;
            }
        }

        if ($spec['paths'] === []) {
            $spec['paths'] = new \stdClass();   // YAML/JSON: {} نه []
        }
        if (($spec['servers'] ?? null) === []) {
            unset($spec['servers']);
        }

        if ($usesAuth) {
            $spec['components'] = [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
            ];
        }

        return $spec;
    }

    /** @param array<string, mixed> $spec */
    public function toJson(array $spec): string
    {
        return (string) \json_encode(
            $spec,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
    }

    /** @param array<string, mixed> $spec */
    public function toYaml(array $spec): string
    {
        return Yaml::emit($spec);
    }

    // ─── درونی ───────────────────────────────────────────────────────────

    /**
     * @return list<Route>
     */
    public function routesForVersion(string $version, ?string $prefix = null): array
    {
        $prefix = \trim((string) ($prefix ?? $this->config['prefix'] ?? 'api'), '/');
        $uriPrefix = $prefix === '' ? $version : $prefix . '/' . $version;
        $namePrefix = 'api.' . $version . '.';

        $out = [];
        foreach ($this->router->routes()->all() as $route) {
            $uri = \trim($route->uri(), '/');
            $name = (string) ($route->getName() ?? '');

            if (\str_starts_with($uri, $uriPrefix . '/') || $uri === $uriPrefix || \str_starts_with($name, $namePrefix)) {
                $out[] = $route;
            }
        }

        return $out;
    }

    /** `api/v1/products/{product}` → `/products/{product}` (بدون پیشوند نگارش). */
    public function convertPath(string $uri): string
    {
        $uri = '/' . \trim($uri, '/');
        $prefix = \trim((string) ($this->config['prefix'] ?? 'api'), '/');

        // حذف «/{prefix}/v{N}» از ابتدای مسیر
        $pattern = '#^/' . \preg_quote($prefix === '' ? 'api' : $prefix, '#') . '/v\d+(?=/|$)#';
        $converted = (string) \preg_replace($pattern, '', $uri);
        if ($converted === '') {
            $converted = '/';
        }

        return $converted;
    }

    private function normalizeVersion(string $version): string
    {
        $version = \trim(\mb_strtolower($version));

        return \str_starts_with($version, 'v') ? $version : 'v' . $version;
    }

    /** @return \ReflectionFunctionAbstract|null */
    private function reflectAction(mixed $action): ?\ReflectionFunctionAbstract
    {
        try {
            if ($action instanceof \Closure) {
                return new \ReflectionFunction($action);
            }
            if (\is_string($action)) {
                if (\str_contains($action, '@')) {
                    [$class, $method] = \explode('@', $action, 2);
                    if (\class_exists($class) && \method_exists($class, $method)) {
                        return new \ReflectionMethod($class, $method);
                    }
                } elseif (\str_contains($action, '::')) {
                    [$class, $method] = \explode('::', $action, 2);
                    if (\class_exists($class) && \method_exists($class, $method)) {
                        return new \ReflectionMethod($class, $method);
                    }
                } elseif (\class_exists($action) && \method_exists($action, '__invoke')) {
                    return new \ReflectionMethod($action, '__invoke');
                }
            }
            if (\is_array($action) && \count($action) === 2) {
                [$class, $method] = $action;
                $class = \is_object($class) ? $class::class : (string) $class;
                if (\class_exists($class) && \method_exists($class, (string) $method)) {
                    return new \ReflectionMethod($class, (string) $method);
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @template T of object
     * @param class-string<T>      $class
     * @return list<T>
     */
    private function attributes(\ReflectionFunctionAbstract $ref, string $class): array
    {
        $out = [];
        foreach ($ref->getAttributes($class) as $attr) {
            try {
                $out[] = $attr->newInstance();
            } catch (\Throwable) {
                // attribute نامعتبر → نادیده
            }
        }

        return $out;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    private function firstAttribute(\ReflectionFunctionAbstract $ref, string $class): ?object
    {
        $all = $this->attributes($ref, $class);

        return $all[0] ?? null;
    }

    /**
     * @param list<ApiParam> $paramAttrs
     *
     * @return list<array<string, mixed>>
     */
    private function pathParameters(Route $route, array $paramAttrs): array
    {
        $parameters = [];
        $wheres = $route->wheres();

        // پارامترهای مسیر (از خود route)
        foreach ($route->parameterNames() as $name) {
            $schema = $this->schemaFromWhere((string) ($wheres[$name] ?? ''));
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => $schema,
            ];
        }

        // پارامترهای دستی (query/header/cookie یا override مسیر)
        foreach ($paramAttrs as $p) {
            if ($p->in === 'path') {
                foreach ($parameters as $i => $existing) {
                    if ($existing['name'] === $p->name) {
                        unset($parameters[$i]);
                    }
                }
            }
            $schema = ['type' => $p->type];
            if ($p->format !== null && $p->format !== '') {
                $schema['format'] = $p->format;
            }
            if ($p->enum !== []) {
                $schema['enum'] = $p->enum;
            }
            $entry = [
                'name' => $p->name,
                'in' => $p->in,
                'required' => $p->required || $p->in === 'path',
                'schema' => $schema,
            ];
            if ($p->description !== '') {
                $entry['description'] = $p->description;
            }
            $parameters[] = $entry;
        }

        return \array_values($parameters);
    }

    /** @return array<string, mixed> */
    private function schemaFromWhere(string $where): array
    {
        return match (true) {
            $where === '[0-9]+' => ['type' => 'integer'],
            $where === '[a-zA-Z]+' => ['type' => 'string'],
            $where === '[a-zA-Z0-9]+' => ['type' => 'string'],
            $where !== '' && \str_contains($where, '9a-fA-F') && \str_contains($where, '{8}') => ['type' => 'string', 'format' => 'uuid'],
            default => ['type' => 'string'],
        };
    }

    /** @return array<string, mixed>|null */
    private function requestBody(?\ReflectionFunctionAbstract $ref, string $method): ?array
    {
        if (!\in_array($method, ['post', 'put', 'patch', 'delete'], true)) {
            return null;
        }

        // FormRequest در امضای متد → schema از rules()
        if ($ref !== null) {
            foreach ($ref->getParameters() as $param) {
                $type = $param->getType();
                if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }
                $className = $type->getName();
                if (!\class_exists($className) || !\is_subclass_of($className, FormRequest::class)) {
                    continue;
                }

                $schema = $this->schemaFromFormRequest($className);
                if ($schema !== null) {
                    return [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => $schema]],
                    ];
                }
            }
        }

        // بدنهٔ عمومی
        return [
            'required' => false,
            'content' => ['application/json' => ['schema' => ['type' => 'object']]],
        ];
    }

    /** @param class-string<FormRequest> $class */
    private function schemaFromFormRequest(string $class): ?array
    {
        try {
            $ref = new \ReflectionClass($class);
            $probe = $ref->newInstanceWithoutConstructor();
            $rules = (array) $probe->rules();
        } catch (\Throwable) {
            return null;
        }

        $properties = [];
        $required = [];
        foreach ($rules as $field => $rule) {
            $field = (string) $field;
            $ruleText = \is_array($rule) ? \implode('|', \array_map('strval', $rule)) : (string) $rule;
            $parts = \array_map(\trim(...), \explode('|', $ruleText));

            $isRequired = \in_array('required', $parts, true);
            if ($isRequired) {
                $required[] = $field;
            }

            $properties[$field] = $this->schemaFromRules($parts);
        }

        $schema = ['type' => 'object', 'properties' => $properties === [] ? new \stdClass() : $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @param list<string> $parts
     *
     * @return array<string, mixed>
     */
    private function schemaFromRules(array $parts): array
    {
        $schema = ['type' => 'string'];
        foreach ($parts as $rule) {
            $name = \mb_strtolower((string) (\explode(':', $rule, 2)[0] ?? ''));
            $arg = \str_contains($rule, ':') ? (string) \explode(':', $rule, 2)[1] : '';

            match ($name) {
                'integer', 'int' => $schema = ['type' => 'integer'],
                'numeric', 'decimal' => $schema = ['type' => 'number'],
                'boolean', 'bool' => $schema = ['type' => 'boolean'],
                'array' => $schema = ['type' => 'array', 'items' => new \stdClass()],
                'date', 'date_format' => $schema = ['type' => 'string', 'format' => 'date-time'],
                'email' => $schema = ['type' => 'string', 'format' => 'email'],
                'url', 'active_url' => $schema = ['type' => 'string', 'format' => 'uri'],
                'uuid' => $schema = ['type' => 'string', 'format' => 'uuid'],
                'image', 'file', 'mimes' => $schema = ['type' => 'string', 'format' => 'binary'],
                'in' => $schema = ['type' => 'string', 'enum' => \explode(',', $arg)],
                'max' => $schema = [...$schema, ...(is_numeric($arg) ? (\in_array($schema['type'] ?? '', ['string'], true) ? ['maxLength' => (int) $arg] : ['maximum' => (int) $arg]) : [])],
                'min' => $schema = [...$schema, ...(is_numeric($arg) ? (\in_array($schema['type'] ?? '', ['string'], true) ? ['minLength' => (int) $arg] : ['minimum' => (int) $arg]) : [])],
                'nullable' => $schema = [...$schema, 'nullable' => true],
                default => null,
            };
        }

        return $schema;
    }

    /**
     * @param list<ApiResponse> $attrs
     *
     * @return array<string, mixed>
     */
    private function responses(array $attrs): array
    {
        $responses = [];
        foreach ($attrs as $attr) {
            $entry = ['description' => $attr->description !== '' ? $attr->description : $this->defaultDescription($attr->status)];
            if ($attr->status !== 204) {
                $schema = ['type' => 'object'];
                if ($attr->resource !== null && $attr->resource !== '') {
                    $schema['x-tavaan-resource'] = $attr->resource;
                }
                $entry['content'] = [$attr->contentType => ['schema' => $schema]];
            }
            $responses[(string) $attr->status] = $entry;
        }

        if ($responses === []) {
            $responses = [
                '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
            ];
        }

        return $responses;
    }

    private function defaultDescription(int $status): string
    {
        return match ($status) {
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'Response',
        };
    }

    private function routeUsesAuth(Route $route): bool
    {
        foreach ($route->getMiddleware() as $mw) {
            $name = (string) (\explode(':', (string) $mw, 2)[0] ?? '');
            if ($name === 'auth' || $name === 'auth.basic' || \str_ends_with($name, 'AuthenticateMiddleware')) {
                return true;
            }
        }

        return false;
    }

    private function deriveTag(Route $route, string $prefix, string $version): string
    {
        $segments = \array_values(\array_filter(\explode('/', \trim($route->uri(), '/'))));
        // api/v1/products/… → products
        $expected = $prefix === '' ? ['api', $version] : [$prefix, $version];
        foreach ($segments as $i => $seg) {
            if (\str_starts_with($seg, '{')) {
                continue;
            }
            if ($i < \count($expected) && $seg === $expected[$i]) {
                continue;
            }

            return $seg;
        }

        return 'default';
    }

    private function deriveOperationId(string $method, string $path): string
    {
        $clean = \trim((string) \preg_replace('/[^A-Za-z0-9]+/', '.', $path), '.');
        $parts = \explode('.', $clean);
        $camel = '';
        foreach ($parts as $part) {
            $camel .= $camel === '' ? $part : \ucfirst($part);
        }

        return \mb_strtolower($method) . ($camel === '' ? 'Root' : \ucfirst($camel));
    }
}

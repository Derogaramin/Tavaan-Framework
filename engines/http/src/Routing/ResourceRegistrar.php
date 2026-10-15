<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

/**
 * تَوان | Tavaan — Routing
 * ResourceRegistrar — ثبت روت‌های RESTful (DOC-040 §3).
 *
 * resource: index/create/store/show/edit/update/destroy
 * apiResource: index/store/show/update/destroy
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class ResourceRegistrar
{
    /** @var array<string, array{uri: string, methods: list<string>, action: string}> */
    private const WEB_ACTIONS = [
        'index'   => ['uri' => '',            'methods' => ['GET'],            'action' => 'index'],
        'create'  => ['uri' => '/create',     'methods' => ['GET'],            'action' => 'create'],
        'store'   => ['uri' => '',            'methods' => ['POST'],           'action' => 'store'],
        'show'    => ['uri' => '/{resource}', 'methods' => ['GET'],            'action' => 'show'],
        'edit'    => ['uri' => '/{resource}/edit', 'methods' => ['GET'],       'action' => 'edit'],
        'update'  => ['uri' => '/{resource}', 'methods' => ['PUT', 'PATCH'],   'action' => 'update'],
        'destroy' => ['uri' => '/{resource}', 'methods' => ['DELETE'],         'action' => 'destroy'],
    ];

    /** @var array<string, array{uri: string, methods: list<string>, action: string}> */
    private const API_ACTIONS = [
        'index'   => ['uri' => '',            'methods' => ['GET'],          'action' => 'index'],
        'store'   => ['uri' => '',            'methods' => ['POST'],         'action' => 'store'],
        'show'    => ['uri' => '/{resource}', 'methods' => ['GET'],          'action' => 'show'],
        'update'  => ['uri' => '/{resource}', 'methods' => ['PUT', 'PATCH'], 'action' => 'update'],
        'destroy' => ['uri' => '/{resource}', 'methods' => ['DELETE'],       'action' => 'destroy'],
    ];

    public function __construct(private readonly Router $router)
    {
    }

    /**
     * @param class-string $controller
     * @param list<string> $only
     * @param list<string> $except
     */
    public function register(string $name, string $controller, bool $api = false, array $only = [], array $except = []): self
    {
        $base = \trim($name, '/');
        // photos → photo (نام پارامتر مفرد ساده)
        $param = \rtrim($base, 's') === '' ? $base : \rtrim($base, 's');
        // نام‌های dot‌دار (nested): posts.comments → prefix + param
        $segments = \explode('.', $base);
        $prefix = '';
        foreach (\array_slice($segments, 0, -1) as $parent) {
            $parentParam = \rtrim($parent, 's');
            $prefix .= $parent . '/{' . $parentParam . '}/';
        }
        $last = \end($segments) ?: $base;
        $param = \rtrim($last, 's') === '' ? $last : \rtrim($last, 's');
        $namePrefix = \str_replace('.', '.', $base) . '.';

        $actions = $api ? self::API_ACTIONS : self::WEB_ACTIONS;

        foreach ($actions as $key => $spec) {
            if ($only !== [] && !\in_array($key, $only, true)) {
                continue;
            }
            if (\in_array($key, $except, true)) {
                continue;
            }

            $uri = '/' . \trim($prefix . $last . \str_replace('{resource}', '{' . $param . '}', $spec['uri']), '/');
            $route = $this->router->addRoute($spec['methods'], $uri, [$controller, $spec['action']]);
            $route->name($namePrefix . $key);
        }

        return $this;
    }
}

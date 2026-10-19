<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;
use Tavaan\Support\Str;

/**
 * تَوان | Tavaan — Scaffold
 * ControllerGenerator — `make:controller Shop/ProductController [--resource] [--api] [--model=Product]`.
 *
 * بدون flag → کنترلر `__invoke`؛ با `--resource` اکشن‌های CRUD (وب: view/redirect،
 * با `--api` پاسخ JSON — DOC-077 §2/§4).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class ControllerGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $model = $cmd->opt('model');

        return $this->files(
            $cmd->nameArgument(),
            $cmd->moduleOption(),
            resource: (bool) $cmd->opt('resource'),
            api: (bool) $cmd->opt('api'),
            model: \is_string($model) && $model !== '' ? $model : null,
        );
    }

    /**
     * @return array<string, string>
     */
    public function files(string $name, ?string $module, bool $resource = false, bool $api = false, ?string $model = null): array
    {
        $segments = $this->validateNamespacedName($name);
        $class = (string) \array_pop($segments);
        if (!\str_ends_with($class, 'Controller')) {
            $class .= 'Controller';
        }

        $context = $this->contextFor($module);
        $namespace = $context['namespace'] . '\\Http\\Controllers' . ($segments === [] ? '' : '\\' . \implode('\\', $segments));
        $subPath = $segments === [] ? '' : '/' . \implode('/', $segments);

        $model ??= (string) \preg_replace('/Controller$/', '', $class);
        $modelPlural = Str::plural($model);

        $content = $this->render('controller.stub', [
            'namespace' => $namespace,
            'class' => $class,
            'module' => $context['module'] ?? '',
            'resourceApi' => $resource && $api,
            'resourceWeb' => $resource && !$api,
            'invokable' => !$resource,
            'model' => $model,
            'modelPlural' => $modelPlural,
            'viewPrefix' => Str::kebab($modelPlural) . '.',
            'routePrefix' => Str::kebab($modelPlural),
        ]);

        return [$this->target($context['src'] . '/Http/Controllers' . $subPath . '/' . $class . '.php') => $content];
    }
}

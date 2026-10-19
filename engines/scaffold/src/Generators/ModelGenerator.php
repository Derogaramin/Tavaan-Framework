<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;
use Tavaan\Support\Str;

/**
 * تَوان | Tavaan — Scaffold
 * ModelGenerator — `make:model Product [--migration --factory --seeder --controller]`.
 *
 * flagهای همراه، Generatorهای خواهر را با همان stubها/basePath فراخوانی
 * می‌کنند (الگوی DOC-077 §5). `--fields=name,price:integer` هم fillable و هم
 * ستون‌های migration را می‌سازد.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class ModelGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $segments = $this->validateNamespacedName($cmd->nameArgument());
        $class = (string) \array_pop($segments);
        $context = $this->context($cmd);

        $namespace = $context['namespace'] . '\\Models' . ($segments === [] ? '' : '\\' . \implode('\\', $segments));
        $subPath = $segments === [] ? '' : '/' . \implode('/', $segments);
        $table = Str::snake(Str::plural($class));

        $fieldsValue = $cmd->opt('fields');
        $fields = MigrationGenerator::parseFields(\is_string($fieldsValue) ? $fieldsValue : '');
        $fillable = $fields === [] ? '[]' : "['" . \implode("', '", \array_column($fields, 'name')) . "']";

        $files = [
            $this->target($context['src'] . '/Models' . $subPath . '/' . $class . '.php') => $this->render('model.stub', [
                'namespace' => $namespace,
                'class' => $class,
                'module' => $context['module'] ?? '',
                'table' => $table,
                'fillable' => $fillable,
            ]),
        ];

        // flagهای همراه (DOC-077 §2: make:model Product --migration --factory --seeder --controller)
        if ($cmd->opt('migration')) {
            $migration = new MigrationGenerator($this->stubs, $this->basePath);
            $files += $migration->files('create_' . $table . '_table', $context['module'], $fields, $table);
        }
        if ($cmd->opt('factory')) {
            $factory = new FactoryGenerator($this->stubs, $this->basePath);
            $files += $factory->files($class . 'Factory', $class, $context['module']);
        }
        if ($cmd->opt('seeder')) {
            $seeder = new SeederGenerator($this->stubs, $this->basePath);
            $files += $seeder->files($class . 'Seeder', $table, $context['module']);
        }
        if ($cmd->opt('controller')) {
            $controller = new ControllerGenerator($this->stubs, $this->basePath);
            $files += $controller->files($class . 'Controller', $context['module'], resource: true, api: (bool) $cmd->opt('api'), model: $class);
        }

        return $files;
    }
}

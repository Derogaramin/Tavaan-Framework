<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;

/**
 * تَوان | Tavaan — Scaffold
 * FactoryGenerator — `make:factory ProductFactory [--model=Product] [--module=shop]`.
 *
 * مسیر `Database/Factories` با قرارداد نامِ `Tavaan\Testing\Database\Factory::for()`
 * هم‌خوان است (DOC-084E §6) → `Product::factory()` خودکار resolve می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class FactoryGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $class = $this->validateClassName($cmd->nameArgument());
        if (!\str_ends_with($class, 'Factory')) {
            $class .= 'Factory';
        }

        $model = $cmd->opt('model');
        $model = \is_string($model) && $model !== ''
            ? $this->validateClassName($model)
            : (string) \preg_replace('/Factory$/', '', $class);

        $context = $this->context($cmd);

        return $this->files($class, $model, $context['module']);
    }

    /**
     * @return array<string, string>
     */
    public function files(string $class, string $model, ?string $module): array
    {
        $class = $this->validateClassName($class);
        $model = $this->validateClassName($model);
        $context = $this->contextFor($module);

        $modelImport = $context['namespace'] . '\\Models\\' . $model;

        return [
            $this->target($context['src'] . '/Database/Factories/' . $class . '.php') => $this->render('factory.stub', [
                'namespace' => $context['namespace'] . '\\Database\\Factories',
                'class' => $class,
                'model' => $model,
                'modelImport' => $modelImport,
                'module' => $context['module'] ?? '',
            ]),
        ];
    }
}

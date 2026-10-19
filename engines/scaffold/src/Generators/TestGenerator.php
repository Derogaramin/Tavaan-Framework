<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;
use Tavaan\Support\Str;

/**
 * تَوان | Tavaan — Scaffold
 * TestGenerator — `make:test ProductTest [--unit] [--module=shop]`.
 *
 * هدف: `tests/Unit|Feature` (اپ) یا `modules/<m>/tests/Unit|Feature` —
 * هر دو مسیر توسط `tvn test` کشف می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class TestGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $class = $this->validateClassName($cmd->nameArgument());
        if (!\str_ends_with($class, 'Test')) {
            $class .= 'Test';
        }

        $unit = (bool) $cmd->opt('unit');
        $layer = $unit ? 'Unit' : 'Feature';
        $module = $cmd->moduleOption();

        if ($module === null) {
            $namespace = 'App\\Tests\\' . $layer;
            $path = 'tests/' . $layer . '/' . $class . '.php';
        } else {
            $slug = $this->validateKebab($module, 'module');
            $namespace = 'Modules\\' . Str::pascal(\str_replace('-', ' ', $slug)) . '\\Tests\\' . $layer;
            $path = 'modules/' . $slug . '/tests/' . $layer . '/' . $class . '.php';
        }

        return [
            $this->target($path) => $this->render($unit ? 'test-unit.stub' : 'test-feature.stub', [
                'namespace' => $namespace,
                'class' => $class,
                'module' => $module ?? '',
            ]),
        ];
    }
}

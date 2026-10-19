<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;

/**
 * تَوان | Tavaan — Scaffold
 * ResourceGenerator — `make:resource ProductResource [--module=shop]`.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class ResourceGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $class = $this->validateClassName($cmd->nameArgument());
        if (!\str_ends_with($class, 'Resource')) {
            $class .= 'Resource';
        }

        $context = $this->context($cmd);

        return [
            $this->target($context['src'] . '/Http/Resources/' . $class . '.php') => $this->render('resource.stub', [
                'namespace' => $context['namespace'] . '\\Http\\Resources',
                'class' => $class,
                'module' => $context['module'] ?? '',
            ]),
        ];
    }
}

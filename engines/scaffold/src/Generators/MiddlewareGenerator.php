<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;

/**
 * تَوان | Tavaan — Scaffold
 * MiddlewareGenerator — `make:middleware EnsureShopEnabled [--module=shop]`.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MiddlewareGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $class = $this->validateClassName($cmd->nameArgument());
        if (!\str_ends_with($class, 'Middleware')) {
            $class .= 'Middleware';
        }

        $context = $this->context($cmd);

        return [
            $this->target($context['src'] . '/Http/Middleware/' . $class . '.php') => $this->render('middleware.stub', [
                'namespace' => $context['namespace'] . '\\Http\\Middleware',
                'class' => $class,
                'module' => $context['module'] ?? '',
            ]),
        ];
    }
}

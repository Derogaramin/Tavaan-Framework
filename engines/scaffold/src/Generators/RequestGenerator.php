<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;

/**
 * تَوان | Tavaan — Scaffold
 * RequestGenerator — `make:request StoreProductRequest [--module=shop]`.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class RequestGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $class = $this->validateClassName($cmd->nameArgument());
        if (!\str_ends_with($class, 'Request')) {
            $class .= 'Request';
        }

        $context = $this->context($cmd);

        return [
            $this->target($context['src'] . '/Http/Requests/' . $class . '.php') => $this->render('request.stub', [
                'namespace' => $context['namespace'] . '\\Http\\Requests',
                'class' => $class,
                'module' => $context['module'] ?? '',
            ]),
        ];
    }
}

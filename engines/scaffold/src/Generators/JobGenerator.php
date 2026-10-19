<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;

/**
 * تَوان | Tavaan — Scaffold
 * JobGenerator — `make:job SendInvoiceEmail [--module=shop]`.
 *
 * الگو: `implements ShouldQueue` + `use Queueable` + `handle()` (DOC-061 §3).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class JobGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $class = $this->validateClassName($cmd->nameArgument());
        if (!\str_ends_with($class, 'Job')) {
            $class .= 'Job';
        }

        $context = $this->context($cmd);

        return [
            $this->target($context['src'] . '/Jobs/' . $class . '.php') => $this->render('job.stub', [
                'namespace' => $context['namespace'] . '\\Jobs',
                'class' => $class,
                'module' => $context['module'] ?? '',
            ]),
        ];
    }
}

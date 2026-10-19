<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;
use Tavaan\Support\Str;

/**
 * تَوان | Tavaan — Scaffold
 * SeederGenerator — `make:seeder ProductSeeder [--module=shop]`.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class SeederGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $class = $this->validateClassName($cmd->nameArgument());
        if (!\str_ends_with($class, 'Seeder')) {
            $class .= 'Seeder';
        }

        $table = Str::snake(Str::plural((string) \preg_replace('/Seeder$/', '', $class)));
        $context = $this->context($cmd);

        return $this->files($class, $table, $context['module']);
    }

    /**
     * @return array<string, string>
     */
    public function files(string $class, string $table, ?string $module): array
    {
        $class = $this->validateClassName($class);
        $context = $this->contextFor($module);

        return [
            $this->target($context['src'] . '/Database/Seeders/' . $class . '.php') => $this->render('seeder.stub', [
                'namespace' => $context['namespace'] . '\\Database\\Seeders',
                'class' => $class,
                'table' => $table,
                'module' => $context['module'] ?? '',
            ]),
        ];
    }
}

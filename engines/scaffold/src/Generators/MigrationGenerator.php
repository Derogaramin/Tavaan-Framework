<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;

/**
 * تَوان | Tavaan — Scaffold
 * MigrationGenerator — `make:migration create_products_table [--module=shop] [--fields=…] [--table=…]`.
 *
 * فایل migration با پیشوند زمانی (`Y_m_d_His`) برای ترتیب قطعی Migrator و
 * بدنهٔ anonymous-class (الگوی migrationهای خود موتورها).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class MigrationGenerator extends Generator
{
    /** @var list<string> انواع مجاز ستون (متدهای Blueprint) */
    private const COLUMN_TYPES = ['string', 'text', 'integer', 'bigInteger', 'boolean', 'json'];

    public function generate(GenerateCommand $cmd): array
    {
        $name = $this->validateSnake($cmd->nameArgument(), 'migration');
        $context = $this->context($cmd);

        $table = $cmd->opt('table');
        $table = \is_string($table) && $table !== '' ? $table : null;
        $fieldsValue = $cmd->opt('fields');
        $fields = self::parseFields(\is_string($fieldsValue) ? $fieldsValue : '');

        return $this->files($name, $context['module'], $fields, $table);
    }

    /**
     * @param list<array{name: string, type: string}> $columns
     *
     * @return array<string, string>
     */
    public function files(string $migrationName, ?string $module, array $columns = [], ?string $table = null): array
    {
        $migrationName = $this->validateSnake($migrationName, 'migration');
        $table ??= $this->guessTable($migrationName);
        $timestamp = \date('Y_m_d_His');
        $dir = $module === null
            ? 'database/migrations'
            : 'modules/' . $this->validateKebab($module, 'module') . '/migrations';

        $content = $this->render('migration-create.stub', [
            'table' => $table,
            'columns' => $columns,
        ]);

        return [$this->target($dir . '/' . $timestamp . '_' . $migrationName . '.php') => $content];
    }

    /**
     * پارس `--fields=name,price:integer,active:boolean`.
     *
     * @return list<array{name: string, type: string}>
     */
    public static function parseFields(string $spec): array
    {
        $fields = [];
        foreach (\array_filter(\array_map('trim', \explode(',', $spec))) as $part) {
            [$name, $type] = \array_pad(\explode(':', $part, 2), 2, 'string');
            $name = \trim($name);
            $type = \trim($type);
            if ($name === '' || \preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
                continue;
            }
            $fields[] = ['name' => $name, 'type' => \in_array($type, self::COLUMN_TYPES, true) ? $type : 'string'];
        }

        return $fields;
    }

    /** `create_products_table` → `products`؛ وگرنه خود نام. */
    private function guessTable(string $migrationName): string
    {
        if (\preg_match('/^create_(.+)_table$/', $migrationName, $m) === 1) {
            return $m[1];
        }
        if (\preg_match('/^add_(.+)_to_(.+)_table$/', $migrationName, $m) === 1) {
            return $m[2];
        }

        return $migrationName;
    }
}

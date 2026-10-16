<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Schema;

/**
 * تَوان | Tavaan — Schema
 * Blueprint — تعریف PHP ساختار جدول (ستون‌ها + ایندکس‌ها + FK — DOC-048 §2).
 *
 * ### مثال
 * ```php
 * Schema::create('users', function (Blueprint $t): void {
 *     $t->ulid('id')->primary();
 *     $t->string('email')->unique();
 *     $t->timestamps(3);
 * });
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/schema.md
 */
final class Blueprint
{
    /** @var array<string, ColumnDefinition> */
    private array $columns = [];

    /** @var list<array<string, mixed>> */
    private array $commands = [];

    /** @var list<string> */
    private array $droppedColumns = [];

    /** @var array<string, string> column => newName */
    private array $renamedColumns = [];

    public function __construct(
        public readonly string $table,
        private readonly bool $creating = false,
    ) {
    }

    public function isCreating(): bool
    {
        return $this->creating;
    }

    /** @return array<string, ColumnDefinition> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @return list<array<string, mixed>> */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /** @return list<string> */
    public function getDroppedColumns(): array
    {
        return $this->droppedColumns;
    }

    /** @return array<string, string> */
    public function getRenamedColumns(): array
    {
        return $this->renamedColumns;
    }

    public function addColumn(ColumnDefinition $column): ColumnDefinition
    {
        $this->columns[$column->name] = $column;

        return $column;
    }

    // ─── شناسه‌ها ─────────────────────────────────────────────────────────

    /** bigIncrements با نام id (سازگار با ADR-0006: ULID ترجیح دارد). */
    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($name);
    }

    public function ulid(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'ulid'));
    }

    public function uuid(string $name = 'uuid'): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'uuid'));
    }

    public function bigIncrements(string $name = 'id'): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'bigInteger');
        $column->autoIncrement = true;
        $column->unsigned = true;
        $column->primary = true;

        return $this->addColumn($column);
    }

    public function increments(string $name = 'id'): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'integer');
        $column->autoIncrement = true;
        $column->unsigned = true;
        $column->primary = true;

        return $this->addColumn($column);
    }

    public function foreignId(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'bigInteger');
        $column->unsigned = true;

        return $this->addColumn($column);
    }

    public function foreignUlid(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'ulid'));
    }

    public function foreignUuid(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'uuid'));
    }

    // ─── رشته/متن ─────────────────────────────────────────────────────────

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'string');
        $column->length = $length;

        return $this->addColumn($column);
    }

    public function char(string $name, int $length = 255): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'char');
        $column->length = $length;

        return $this->addColumn($column);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'text'));
    }

    public function mediumText(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'mediumText'));
    }

    public function longText(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'longText'));
    }

    // ─── عددی ─────────────────────────────────────────────────────────────

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'integer'));
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'bigInteger'));
    }

    public function unsignedBigInteger(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'bigInteger');
        $column->unsigned = true;

        return $this->addColumn($column);
    }

    public function unsignedInteger(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'integer');
        $column->unsigned = true;

        return $this->addColumn($column);
    }

    public function smallInteger(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'smallInteger'));
    }

    public function tinyInteger(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'tinyInteger'));
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'boolean'));
    }

    /** 🔒 پول همیشه decimal (نه float — ADR-0013). */
    public function decimal(string $name, int $precision = 18, int $scale = 4): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'decimal');
        $column->precision = $precision;
        $column->scale = $scale;

        return $this->addColumn($column);
    }

    public function double(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'double'));
    }

    public function float(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'float'));
    }

    // ─── تاریخ/زمان ───────────────────────────────────────────────────────

    public function date(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'date'));
    }

    public function dateTime(string $name, int $precision = 0): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'dateTime');
        $column->precision = $precision;

        return $this->addColumn($column);
    }

    public function timestamp(string $name, int $precision = 0): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'timestamp');
        $column->precision = $precision;

        return $this->addColumn($column);
    }

    public function time(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'time'));
    }

    public function year(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'year'));
    }

    /** created_at + updated_at (UTC — ذخیره با Clock). */
    public function timestamps(int $precision = 0): void
    {
        $this->timestamp('created_at', $precision)->nullable();
        $this->timestamp('updated_at', $precision)->nullable();
    }

    public function softDeletes(string $column = 'deleted_at', int $precision = 0): ColumnDefinition
    {
        return $this->timestamp($column, $precision)->nullable()->index();
    }

    // ─── ساخت‌یافته ──────────────────────────────────────────────────────

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'json'));
    }

    public function jsonb(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'jsonb'));
    }

    /** @param list<string> $values */
    public function enum(string $name, array $values): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'enum');
        $column->enumValues = $values;

        return $this->addColumn($column);
    }

    /** @param list<string> $values */
    public function set(string $name, array $values): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'set');
        $column->enumValues = $values;

        return $this->addColumn($column);
    }

    public function blob(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'blob'));
    }

    public function binary(string $name): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($name, 'binary'));
    }

    // ─── شبکه ─────────────────────────────────────────────────────────────

    public function ipAddress(string $name = 'ip_address'): ColumnDefinition
    {
        return $this->string($name, 45);
    }

    public function macAddress(string $name = 'mac_address'): ColumnDefinition
    {
        return $this->string($name, 17);
    }

    public function rememberToken(): ColumnDefinition
    {
        return $this->string('remember_token', 100)->nullable();
    }

    // ─── Polymorphic ──────────────────────────────────────────────────────

    public function morphs(string $name): void
    {
        $this->string($name . '_type');
        $this->unsignedBigInteger($name . '_id');
        $this->index([$name . '_type', $name . '_id'], $this->table . '_' . $name . '_index');
    }

    public function nullableMorphs(string $name): void
    {
        $this->string($name . '_type')->nullable();
        $this->unsignedBigInteger($name . '_id')->nullable();
        $this->index([$name . '_type', $name . '_id'], $this->table . '_' . $name . '_index');
    }

    public function ulidMorphs(string $name): void
    {
        $this->string($name . '_type');
        $this->ulid($name . '_id');
        $this->index([$name . '_type', $name . '_id'], $this->table . '_' . $name . '_index');
    }

    // ─── ایندکس / کلید ────────────────────────────────────────────────────

    /** @param list<string>|string $columns */
    public function primary(array|string $columns, ?string $name = null): self
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . \implode('_', $columns) . '_primary';
        $this->commands[] = ['type' => 'primary', 'columns' => $columns, 'name' => $name];

        return $this;
    }

    /** @param list<string>|string $columns */
    public function unique(array|string $columns, ?string $name = null): self
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . \implode('_', $columns) . '_unique';
        $this->commands[] = ['type' => 'unique', 'columns' => $columns, 'name' => $name];

        return $this;
    }

    /** @param list<string>|string $columns */
    public function index(array|string $columns, ?string $name = null): self
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . \implode('_', $columns) . '_index';
        $this->commands[] = ['type' => 'index', 'columns' => $columns, 'name' => $name];

        return $this;
    }

    /** @param list<string>|string $columns */
    public function fullText(array|string $columns, ?string $name = null): self
    {
        $columns = \is_array($columns) ? $columns : [$columns];
        $name ??= $this->table . '_' . \implode('_', $columns) . '_fulltext';
        $this->commands[] = ['type' => 'fulltext', 'columns' => $columns, 'name' => $name];

        return $this;
    }

    public function foreign(string $column, ?string $name = null): ForeignKeyDefinition
    {
        $name ??= $this->table . '_' . $column . '_foreign';
        $fk = new ForeignKeyDefinition($column, $name);
        $this->commands[] = ['type' => 'foreign', 'fk' => $fk];

        return $fk;
    }

    // ─── ALTER ────────────────────────────────────────────────────────────

    public function dropColumn(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->droppedColumns[] = $column;
            unset($this->columns[$column]);
        }

        return $this;
    }

    public function renameColumn(string $from, string $to): self
    {
        $this->renamedColumns[$from] = $to;

        return $this;
    }

    public function dropPrimary(?string $name = null): self
    {
        $this->commands[] = ['type' => 'dropPrimary', 'name' => $name ?? $this->table . '_primary'];

        return $this;
    }

    public function dropUnique(?string $name = null): self
    {
        $this->commands[] = ['type' => 'dropUnique', 'name' => $name ?? $this->table . '_unique'];

        return $this;
    }

    public function dropIndex(?string $name = null): self
    {
        $this->commands[] = ['type' => 'dropIndex', 'name' => $name ?? $this->table . '_index'];

        return $this;
    }

    public function dropForeign(?string $name = null): self
    {
        $this->commands[] = ['type' => 'dropForeign', 'name' => $name ?? $this->table . '_foreign'];

        return $this;
    }

    /** freeze همهٔ ستون‌ها پس از compile. */
    public function freeze(): void
    {
        foreach ($this->columns as $column) {
            $column->freeze();
        }
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Schema;

/**
 * تَوان | Tavaan — Schema
 * ColumnDefinition — تعریف یک ستون (DOC-048 §4) با API سیال.
 *
 * تغییرات فقط درون callback ساخت Blueprint رخ می‌دهند و پس از compile
 * بلو‌پرينت هیچ تغییری پذیرفته نمی‌شود (freeze) — بنابراین از بیرون
 * مانند یک Value Object رفتار می‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/schema.md
 */
final class ColumnDefinition
{
    /** @var list<string>|null */
    public ?array $enumValues = null;

    public ?int $length = null;

    public ?int $precision = null;

    public ?int $scale = null;

    public bool $nullable = false;

    public mixed $default = null;

    public bool $hasDefault = false;

    public bool $autoIncrement = false;

    public bool $unsigned = false;

    public bool $unique = false;

    public bool $primary = false;

    public bool $index = false;

    public ?string $after = null;

    public ?string $comment = null;

    public bool $useCurrent = false;

    private bool $frozen = false;

    public function __construct(
        public readonly string $name,
        public readonly string $type,
    ) {
    }

    public function nullable(bool $value = true): self
    {
        $this->assertMutable();
        $this->nullable = $value;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->assertMutable();
        $this->default = $value;
        $this->hasDefault = true;

        return $this;
    }

    /** پیش‌فرض CURRENT_TIMESTAMP (برای timestamp). */
    public function useCurrent(bool $value = true): self
    {
        $this->assertMutable();
        $this->useCurrent = $value;

        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->assertMutable();
        $this->unique = $value;

        return $this;
    }

    public function primary(bool $value = true): self
    {
        $this->assertMutable();
        $this->primary = $value;

        return $this;
    }

    public function index(bool $value = true): self
    {
        $this->assertMutable();
        $this->index = $value;

        return $this;
    }

    public function unsigned(bool $value = true): self
    {
        $this->assertMutable();
        $this->unsigned = $value;

        return $this;
    }

    public function autoIncrement(bool $value = true): self
    {
        $this->assertMutable();
        $this->autoIncrement = $value;

        return $this;
    }

    public function after(string $column): self
    {
        $this->assertMutable();
        $this->after = $column;

        return $this;
    }

    public function comment(string $comment): self
    {
        $this->assertMutable();
        $this->comment = $comment;

        return $this;
    }

    /** @param list<string> $values */
    public function values(array $values): self
    {
        $this->assertMutable();
        $this->enumValues = $values;

        return $this;
    }

    public function length(int $length): self
    {
        $this->assertMutable();
        $this->length = $length;

        return $this;
    }

    /** قفل پس از compile (تغییر بعدی → LogicException). */
    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new \LogicException(\sprintf('Column [%s] is already compiled and frozen.', $this->name));
        }
    }
}

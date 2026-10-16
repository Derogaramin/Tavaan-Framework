<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Schema;

/**
 * تَوان | Tavaan — Schema
 * ForeignKeyDefinition — تعریف سیال کلید خارجی (DOC-048 §2).
 *
 * @api 1.0
 * @see docs/03-engines/schema.md
 */
final class ForeignKeyDefinition
{
    /** @var list<string> */
    private array $references = [];

    private string $onTable = '';

    private string $onDeleteAction = 'restrict';

    private string $onUpdateAction = 'restrict';

    private const ACTIONS = ['cascade', 'restrict', 'set null', 'no action', 'set default'];

    public function __construct(
        public readonly string $column,
        public readonly string $name,
    ) {
    }

    public function references(string ...$columns): self
    {
        $this->references = $columns;

        return $this;
    }

    public function on(string $table): self
    {
        $this->onTable = $table;

        return $this;
    }

    public function onDelete(string $action): self
    {
        return $this->setAction('onDeleteAction', $action);
    }

    public function onUpdate(string $action): self
    {
        return $this->setAction('onUpdateAction', $action);
    }

    /** @return list<string> */
    public function getReferences(): array
    {
        return $this->references;
    }

    public function getOnTable(): string
    {
        return $this->onTable;
    }

    public function getOnDelete(): string
    {
        return $this->onDeleteAction;
    }

    public function getOnUpdate(): string
    {
        return $this->onUpdateAction;
    }

    private function setAction(string $property, string $action): self
    {
        $normalized = \strtolower(\trim($action));
        if (!\in_array($normalized, self::ACTIONS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown FK action [%s].', $action));
        }
        $this->{$property} = $normalized;

        return $this;
    }
}

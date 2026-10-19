<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin;

/**
 * تَوان | Tavaan — Admin
 * Show — تعریف صفحهٔ جزئیات (DOC-078 §2: `$r->show(fn(Show $s) => …)`).
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class Show
{
    /** @var list<string>|null null = همهٔ ستون‌های resource */
    private ?array $fields = null;

    /** @var array<string, list<string>> relation name → ستون‌ها */
    private array $relations = [];

    private ?string $pendingRelation = null;

    /** @param list<string> $fields */
    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /** آغاز تعریف پنل یک رابطه (`->columns([...])` دنبالش می‌آید). */
    public function relation(string $name): self
    {
        $this->pendingRelation = $name;
        $this->relations[$name] ??= [];

        return $this;
    }

    /** @param list<string> $columns */
    public function columns(array $columns): self
    {
        if ($this->pendingRelation !== null) {
            $this->relations[$this->pendingRelation] = $columns;
            $this->pendingRelation = null;
        }

        return $this;
    }

    /** @return list<string>|null */
    public function fieldList(): ?array
    {
        return $this->fields;
    }

    /** @return array<string, list<string>> */
    public function relationPanels(): array
    {
        return $this->relations;
    }
}

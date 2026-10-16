<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\ORM;

use Tavaan\Core\Contracts\Support\Arrayable;
use Tavaan\Core\Contracts\Support\Jsonable;

/**
 * تَوان | Tavaan — Contracts
 * ModelInterface — قرارداد مدل Active Record (DOC-014 §1 / DOC-050).
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
interface ModelInterface extends Arrayable, Jsonable
{
    public function getKey(): int|string|null;

    public function getKeyName(): string;

    public function getTable(): string;

    /** @param array<string, mixed> $attributes */
    public function fill(array $attributes): static;

    public function save(): bool;

    public function delete(): bool;

    public function refresh(): static;

    public function isNew(): bool;

    public function isDirty(?string $attribute = null): bool;

    /** @return array<string, mixed> */
    public function getChanges(): array;

    /** @return array<string, mixed> */
    public function getOriginal(?string $key = null): array;

    public function getAttribute(string $key): mixed;

    public function setAttribute(string $key, mixed $value): static;
}

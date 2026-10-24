<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * Category — Aggregate دسته‌بندی مقالات (DOC-091 §1.1).
 */

namespace Modules\Blog\Domain\Entities;

/**
 * @api 1.0
 * @see modules/blog/README.md
 */
final class Category
{
    private function __construct(
        private readonly ?int $id,
        private string $name,
        private string $slug,
        private ?string $description,
    ) {
    }

    public static function create(string $name, string $slug, ?string $description = null): self
    {
        return new self(null, $name, $slug, $description);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            name: (string) ($row['name'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
            description: isset($row['description']) ? (string) $row['description'] : null,
        );
    }

    public function rename(string $name, ?string $description = null): void
    {
        $this->name = $name;
        if ($description !== null) {
            $this->description = $description;
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
        ];
    }
}

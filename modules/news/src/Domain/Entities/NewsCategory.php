<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * NewsCategory — دستهٔ خبر با ساختار تو‌در‌تو (parent_id — DOC-092 §2).
 */

namespace Modules\News\Domain\Entities;

/**
 * @api 1.0
 * @see modules/news/README.md
 */
final class NewsCategory
{
    private function __construct(
        private readonly ?int $id,
        private string $title,
        private string $slug,
        private ?int $parentId,
        private ?string $description,
    ) {
    }

    public static function create(string $title, string $slug, ?int $parentId = null, ?string $description = null): self
    {
        return new self(null, $title, $slug, $parentId, $description);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            title: (string) ($row['title'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
            parentId: isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            description: isset($row['description']) ? (string) $row['description'] : null,
        );
    }

    public function edit(string $title, ?int $parentId = null, ?string $description = null): void
    {
        $this->title = $title;
        $this->parentId = $parentId;
        if ($description !== null) {
            $this->description = $description;
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function parentId(): ?int
    {
        return $this->parentId;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'parent_id' => $this->parentId,
            'description' => $this->description,
        ];
    }
}

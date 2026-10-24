<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * NewsTag — برچسب خبری (جدول news_tags با prefix ماژول — DoD §3).
 */

namespace Modules\News\Domain\Entities;

/**
 * @api 1.0
 * @see modules/news/README.md
 */
final class NewsTag
{
    private function __construct(
        private readonly ?int $id,
        private string $name,
        private string $slug,
    ) {
    }

    public static function create(string $name, string $slug): self
    {
        return new self(null, $name, $slug);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            name: (string) ($row['name'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
        );
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

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return ['name' => $this->name, 'slug' => $this->slug];
    }
}

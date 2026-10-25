<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * LmsCategory — دستهٔ دوره (جدول lms_categories — افزودنی مستند DOC-095 §2).
 */

namespace Modules\Lms\Domain\Entities;

/**
 * @api 1.0
 */
final class LmsCategory
{
    private function __construct(
        private readonly int $id,
        private string $title,
        private string $slug,
        private ?string $description,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(string $title, string $slug, ?string $description = null, ?\DateTimeImmutable $at = null): self
    {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان دسته نمی‌تواند خالی باشد.');
        }

        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(0, $title, $slug, $description, $now, null);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['title'] ?? ''),
            (string) ($row['slug'] ?? ''),
            isset($row['description']) && $row['description'] !== null ? (string) $row['description'] : null,
            self::date($row['created_at'] ?? null),
            self::date($row['updated_at'] ?? null),
        );
    }

    public function id(): int
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

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
        ];
    }

    private static function fmt(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        try {
            return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}

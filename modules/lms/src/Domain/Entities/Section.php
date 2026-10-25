<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * Section — بخشی از دوره (DOC-095 §1.2: Course → Section → Lesson).
 */

namespace Modules\Lms\Domain\Entities;

/**
 * @api 1.0
 */
final class Section
{
    private function __construct(
        private readonly string $id,
        private readonly string $courseId,
        private string $title,
        private int $position,
    ) {
    }

    public static function create(string $courseId, string $title, int $position = 0, ?\DateTimeImmutable $at = null): self
    {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان بخش نمی‌تواند خالی باشد.');
        }

        return new self(\Tavaan\Support\Ulid::generate($at)->toString(), $courseId, $title, $position);
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['course_id'] ?? ''),
            (string) ($row['title'] ?? ''),
            (int) ($row['position'] ?? 0),
        );
    }

    public function rename(string $title): void
    {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان بخش نمی‌تواند خالی باشد.');
        }
        $this->title = $title;
    }

    public function move(int $position): void
    {
        $this->position = \max(0, $position);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function courseId(): string
    {
        return $this->courseId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function position(): int
    {
        return $this->position;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->courseId,
            'title' => $this->title,
            'position' => $this->position,
        ];
    }
}

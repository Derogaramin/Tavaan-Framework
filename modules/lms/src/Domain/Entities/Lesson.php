<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * Lesson — درس (محتوای ویدیو/متن/آزمون/پیوست — DOC-095 §1.2).
 */

namespace Modules\Lms\Domain\Entities;

use Modules\Comment\Contracts\CommentableInterface;
use Modules\Lms\Domain\Enums\LessonType;

/**
 * Lesson — زیرمجموعهٔ Section؛ موجودیتِ نظرپذیر برای پرسش‌وپاسخ (Q&A).
 *
 * @api 1.0
 */
final class Lesson implements CommentableInterface
{
    private function __construct(
        private readonly string $id,
        private readonly string $sectionId,
        private readonly string $courseId,
        private string $title,
        private string $slug,
        private LessonType $type,
        private ?string $content,
        private ?string $videoUrl,
        private ?string $attachmentPath,
        private ?string $quizId,
        private int $duration,
        private bool $isFreePreview,
        private int $position,
    ) {
    }

    public static function create(
        string $sectionId,
        string $courseId,
        string $title,
        string $slug,
        LessonType $type = LessonType::Text,
        ?string $content = null,
        ?string $videoUrl = null,
        ?string $attachmentPath = null,
        ?string $quizId = null,
        int $duration = 0,
        bool $isFreePreview = false,
        int $position = 0,
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان درس نمی‌تواند خالی باشد.');
        }
        if ($type === LessonType::Quiz && ($quizId === null || $quizId === '')) {
            throw new \DomainException('درسِ نوع آزمون باید به یک آزمون متصل باشد.');
        }

        return new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            $sectionId,
            $courseId,
            $title,
            $slug,
            $type,
            $content,
            $videoUrl,
            $attachmentPath,
            $quizId,
            \max(0, $duration),
            $isFreePreview,
            $position,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['section_id'] ?? ''),
            (string) ($row['course_id'] ?? ''),
            (string) ($row['title'] ?? ''),
            (string) ($row['slug'] ?? ''),
            LessonType::tryFromString((string) ($row['type'] ?? 'text')),
            isset($row['content']) && $row['content'] !== null ? (string) $row['content'] : null,
            isset($row['video_url']) && $row['video_url'] !== null && $row['video_url'] !== '' ? (string) $row['video_url'] : null,
            isset($row['attachment_path']) && $row['attachment_path'] !== null && $row['attachment_path'] !== '' ? (string) $row['attachment_path'] : null,
            isset($row['quiz_id']) && $row['quiz_id'] !== null && $row['quiz_id'] !== '' ? (string) $row['quiz_id'] : null,
            (int) ($row['duration'] ?? 0),
            (bool) ($row['is_free_preview'] ?? false),
            (int) ($row['position'] ?? 0),
        );
    }

    public function revise(
        string $title,
        ?string $slug = null,
        ?LessonType $type = null,
        ?string $content = null,
        ?string $videoUrl = null,
        ?string $attachmentPath = null,
        ?string $quizId = null,
        ?int $duration = null,
        ?bool $isFreePreview = null,
        ?int $position = null,
    ): void {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان درس نمی‌تواند خالی باشد.');
        }

        $this->title = $title;
        $this->slug = $slug ?? $this->slug;
        $this->type = $type ?? $this->type;
        $this->content = $content;
        $this->videoUrl = $videoUrl;
        $this->attachmentPath = $attachmentPath;
        $this->quizId = $quizId;
        $this->duration = \max(0, $duration ?? $this->duration);
        $this->isFreePreview = $isFreePreview ?? $this->isFreePreview;
        $this->position = $position ?? $this->position;

        if ($this->type === LessonType::Quiz && ($this->quizId === null || $this->quizId === '')) {
            throw new \DomainException('درسِ نوع آزمون باید به یک آزمون متصل باشد.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function sectionId(): string
    {
        return $this->sectionId;
    }

    public function courseId(): string
    {
        return $this->courseId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function type(): LessonType
    {
        return $this->type;
    }

    public function content(): ?string
    {
        return $this->content;
    }

    public function videoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function attachmentPath(): ?string
    {
        return $this->attachmentPath;
    }

    public function quizId(): ?string
    {
        return $this->quizId;
    }

    public function duration(): int
    {
        return $this->duration;
    }

    public function isFreePreview(): bool
    {
        return $this->isFreePreview;
    }

    public function position(): int
    {
        return $this->position;
    }

    // ─── CommentableInterface (پرسش‌وپاسخ درس) ────────────────────────────

    public function commentableType(): string
    {
        return 'lms_lesson';
    }

    public function commentableId(): string
    {
        return $this->id;
    }

    public function commentsOpen(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->sectionId,
            'course_id' => $this->courseId,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'content' => $this->content,
            'video_url' => $this->videoUrl,
            'attachment_path' => $this->attachmentPath,
            'quiz_id' => $this->quizId,
            'duration' => $this->duration,
            'is_free_preview' => $this->isFreePreview,
            'position' => $this->position,
        ];
    }
}

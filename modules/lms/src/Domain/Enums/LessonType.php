<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * LessonType — نوع محتوای درس (DOC-095 §1.2: video|text|quiz|attachment).
 */

namespace Modules\Lms\Domain\Enums;

/**
 * @api 1.0
 */
enum LessonType: string
{
    case Video = 'video';
    case Text = 'text';
    case Quiz = 'quiz';
    case Attachment = 'attachment';

    public static function tryFromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Text;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Video => 'ویدیو',
            self::Text => 'متن',
            self::Quiz => 'آزمون',
            self::Attachment => 'پیوست',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Video => '🎬',
            self::Text => '📄',
            self::Quiz => '📝',
            self::Attachment => '📎',
        };
    }
}

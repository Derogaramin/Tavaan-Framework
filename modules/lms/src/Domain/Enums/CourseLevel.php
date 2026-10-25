<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CourseLevel — سطح دوره (DOC-095 §2: beginner|intermediate|advanced).
 */

namespace Modules\Lms\Domain\Enums;

/**
 * @api 1.0
 */
enum CourseLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public static function tryFromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Beginner;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Beginner => 'مقدماتی',
            self::Intermediate => 'متوسط',
            self::Advanced => 'پیشرفته',
        };
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * QuestionType — نوع سؤال آزمون (DOC-095 §2/§3.3).
 */

namespace Modules\Lms\Domain\Enums;

/**
 * نمره‌دهی خودکار: multiple_choice / true_false همیشه؛
 * short_answer فقط وقتی پاسخ مرجع داشته باشد (config: quiz.auto_grade_short_answer)؛
 * descriptive همیشه دستی (توسط مدرس — §3.3).
 *
 * @api 1.0
 */
enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';
    case Descriptive = 'descriptive';

    public static function tryFromString(string $value): self
    {
        return self::tryFrom($value) ?? self::MultipleChoice;
    }

    /** آیا بدون پاسخ مرجع هم قطعا دستی است؟ */
    public function isAlwaysManual(): bool
    {
        return $this === self::Descriptive;
    }

    /** آیا با پاسخ مرجع، خودکار نمره می‌گیرد؟ */
    public function isAutoGradeable(): bool
    {
        return $this === self::MultipleChoice || $this === self::TrueFalse || $this === self::ShortAnswer;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::MultipleChoice => 'چهارگزینه‌ای',
            self::TrueFalse => 'صحیح/غلط',
            self::ShortAnswer => 'پاسخ کوتاه',
            self::Descriptive => 'تشریحی',
        };
    }
}

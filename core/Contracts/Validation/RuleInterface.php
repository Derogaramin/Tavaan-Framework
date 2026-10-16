<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Validation;

/**
 * تَوان | Tavaan — Contracts
 * RuleInterface — قرارداد قاعدهٔ اعتبارسنجی (DOC-014 §2.8 / DOC-051 §4.2).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
interface RuleInterface
{
    public function passes(string $attribute, mixed $value): bool;

    /** کلید ترجمه (messageId) — هرگز متن خام. */
    public function message(): string;

    /** آیا حتی در غیاب مقدار هم اجرا شود (مانند required)؟ */
    public function implicit(): bool;
}

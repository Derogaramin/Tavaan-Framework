<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Validation;

use Tavaan\Core\Contracts\Support\Arrayable;

/**
 * تَوان | Tavaan — Contracts
 * ValidatorInterface — قرارداد اعتبارسنج (DOC-014 §2.8).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
interface ValidatorInterface
{
    /**
     * دادهٔ معتبرشده (فقط فیلدهای قاعده‌دار)؛ در صورت خطا → ValidationException.
     *
     * @return array<string, mixed>
     */
    public function validate(): array;

    /** @return array<string, mixed> */
    public function validated(): array;

    public function fails(): bool;

    public function passes(): bool;

    public function errors(): Arrayable;

    /**
     * افزودن قاعدهٔ سفارشی در زمان اجرا.
     *
     * @param callable(string, mixed, array<int, string>, \Closure): bool $extension
     */
    public function extend(string $rule, callable $extension, ?string $message = null): void;
}

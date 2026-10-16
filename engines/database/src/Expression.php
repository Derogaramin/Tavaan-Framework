<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database;

/**
 * تَوان | Tavaan — Database
 * Expression — عبارت خام SQL (با binding) در Query Builder.
 *
 * 🔒 مقدار کاربر هرگز درون $value الحاق نمی‌شود؛ فقط با $bindings.
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final readonly class Expression implements \Stringable
{
    /** @param array<int|string, mixed> $bindings */
    public function __construct(
        public string $value,
        public array $bindings = [],
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

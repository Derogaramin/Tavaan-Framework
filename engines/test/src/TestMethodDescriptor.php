<?php

declare(strict_types=1);

namespace Tavaan\Testing;

/**
 * تَوان | Tavaan — Testing
 * TestMethodDescriptor — توصیف یک متد تست (نام + گروه‌ها) در TestSuite.
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final readonly class TestMethodDescriptor
{
    /** @param list<string> $groups گروه‌های مؤثر (ضمنی از مسیر + Attributeها) */
    public function __construct(
        public string $name,
        public array $groups = [],
    ) {
    }
}

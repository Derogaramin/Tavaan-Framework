<?php

declare(strict_types=1);

namespace Tavaan\Engine\Audit;

/**
 * تَوان | Tavaan — Audit
 * Observed — Attribute فیلتر فیلدهای ممیزی‌شونده (DOC-058 §4).
 *
 * ```php
 * #[Observed(event: 'updated', fields: ['status', 'total'])]
 * final class Order extends Model { … }
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/audit.md
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Observed
{
    /** @param list<string> $fields */
    public function __construct(
        public string $event = 'updated',
        public array $fields = [],
    ) {
    }
}

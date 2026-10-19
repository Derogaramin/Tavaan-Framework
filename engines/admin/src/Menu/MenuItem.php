<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Menu;

/**
 * تَوان | Tavaan — Admin
 * MenuItem — یک آیتم منوی پیشخوان (DOC-078 §4).
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final readonly class MenuItem
{
    public function __construct(
        public string $label,
        public string $url = '',
        public string $route = '',
        public string $icon = '',
        public int $order = 100,
        public ?string $permission = null,
    ) {
    }
}

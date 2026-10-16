<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

/**
 * Fixture تست ORM — enum وضعیت پست (cast به Enum).
 *
 * @api internal
 */
enum TPostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Published => 'منتشرشده',
            self::Archived => 'بایگانی',
        };
    }
}

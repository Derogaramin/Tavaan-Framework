<?php

declare(strict_types=1);

namespace Tavaan\Core\Container;

/**
 * تَوان | Tavaan — Core
 * Lifetime — عمر یک binding در کانتینر.
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
enum Lifetime: string
{
    /** هر make = نمونهٔ جدید */
    case Transient = 'transient';

    /** یک نمونه برای کل فرایند */
    case Singleton = 'singleton';

    /** یک نمونه به ازای هر Scope فعال (Request/Job/Tenant) */
    case Scoped = 'scoped';

    /** هر make = فراخوانی Closure */
    case Factory = 'factory';
}

<?php

declare(strict_types=1);

namespace Tavaan\Testing;

/**
 * تَوان | Tavaan — Testing
 * Group — برچسب گروه‌بندی تست (`tvn test --group=cache` — DOC-084E §7).
 *
 * روی کلاس یا متد تست (تکرارپذیر) نصب می‌شود. گروه‌های ضمنی نیز از مسیر
 * فایل استخراج می‌شوند: slug موتور (`engines/<slug>/…`) و `unit`/`feature`/`app`.
 *
 * ### مثال
 * ```php
 * #[Group('cache')]
 * final class CacheStoreTest extends TestCase { … }
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Group
{
    public function __construct(public readonly string $name)
    {
    }
}

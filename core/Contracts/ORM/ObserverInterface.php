<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\ORM;

/**
 * تَوان | Tavaan — Contracts
 * ObserverInterface — قرارداد ناظر رویدادهای مدل (DOC-050 §6).
 *
 * همهٔ متدها اختیاری‌اند؛ فقط متدهای موجود فراخوانی می‌شوند.
 * متدهای `*ing` می‌توانند با `return false` عملیات را لغو کنند.
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
interface ObserverInterface
{
}

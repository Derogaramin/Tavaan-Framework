<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — رویدادهای زمان‌بندی‌شده (DOC-062 §2)
 *
 * این فایل در boot موتور Schedule بارگذاری می‌شود.
 * متغیر $schedule (ScheduleManager) در دسترس است؛ یا از Facade استفاده کنید:
 *
 *   Schedule::command('orders:cleanup')->daily()->at('03:00');
 *   Schedule::command('backup:run')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
 *   Schedule::job(new SendDigestJob())->hourly();
 *   Schedule::call(fn () => ...)->everyFiveMinutes();
 */

use Tavaan\Engine\Schedule\ScheduleManager;

/** @var ScheduleManager $schedule */

// نمونه (غیرفعال): پاک‌سازی کش قدیمی هر شب ساعت ۰۳:۰۰ به وقت تهران
// $schedule->command('cache:clear')->dailyAt('03:00')->withoutOverlapping();

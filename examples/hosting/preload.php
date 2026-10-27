<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — اسکریپت OPcache Preload (DOC-134 §4.2 / DOC-133 §4)
 *
 * این فایل توسط `opcache.preload` در php.ini فراخوانی می‌شود (یک‌بار هنگام
 * start/reload سرویس PHP). کد هستهٔ تَوان را پیش‌کامپایل می‌کند تا بوت سرد
 * (~۸۰–۱۰۵ms) به ~صفر برسد.
 *
 * ⚠️ نیازمندی‌ها:
 *   - PHP ≥ 7.4 با OPcache
 *   - دسترسی به تنظیم `opcache.preload` در php.ini اصلی (نه .user.ini)
 *   - `opcache.preload_user` = کاربر وب‌سرور (مثلاً USER در هاست اشتراکی)
 *   - این اسکریپت در بافت محدود اجرا می‌شود: بدون $_SERVER/$_GET، بدون خروجی.
 *
 * 📌 تنظیم در php.ini (سطح ideal/heavy):
 *   opcache.preload = /home/USER/tavaan/examples/hosting/preload.php
 *   opcache.preload_user = USER
 *   opcache.preload_user الزامی است وگرنه preload اجرا نمی‌شود (امنیت).
 *
 * 📌 بازتولید: پس از هر `tvn optimize` (که storage/cache/classmap.php را
 *   می‌سازد) و هر دیپلوی، سرویس PHP را reload کنید تا preload دوباره اجرا شود.
 *
 * @see docs/09-references/shared-hosting.md
 */

// ── محافظت: فقط در بافت preload اجرا شود ────────────────────────────────────
if (!\function_exists('opcache_compile_file')) {
    return;   // OPcache فعال نیست — کاری نکن
}

$base = \dirname(__DIR__, 2);
$classmap = $base . '/storage/cache/classmap.php';

$preloaded = 0;
$failed = 0;

// ۱) ابتدا هستهٔ همیشه‌لازم (بدون وابستگی به classmap) — bootstrap + container + core
$coreFiles = [
    $base . '/core/bootstrap.php',
    $base . '/core/Autoloading/Autoloader.php',
    $base . '/core/Autoloading/AutoloaderRegistry.php',
    $base . '/core/Application/Application.php',
    $base . '/core/Application/ApplicationBuilder.php',
    $base . '/core/Container/Container.php',
];

// ۲) سپس کل classmap (هسته + موتورها + ماژول‌های فعال) — از کش autoload
$files = $coreFiles;
if (\is_file($classmap) && \is_readable($classmap)) {
    /** @var array<string,string>|null $map */
    $map = @include $classmap;
    if (\is_array($map)) {
        foreach ($map as $path) {
            if (\is_string($path) && $path !== '') {
                $files[] = $path;
            }
        }
    }
}

// ۳) پیش‌کامپایل — با مهار خطا (یک فایل بد نباید کل preload را بشکند)
foreach ($files as $file) {
    if (!\is_string($file) || !\is_file($file)) {
        continue;
    }
    // فقط فایل‌های داخل درخت پروژه (از پیش‌بارگذاری مسیرهای بیرون خودداری کن)
    $real = \realpath($file);
    if ($real === false || !\str_starts_with($real, $base)) {
        continue;
    }
    try {
        if (@\opcache_compile_file($real)) {
            $preloaded++;
        } else {
            $failed++;
        }
    } catch (\Throwable) {
        $failed++;   // خطای کامپایل یک فایل — رد شو
    }
}

// خروجی در بافت preload مجاز نیست؛ فقط در CLI (برای اشکال‌زدایی دستی) لاگ کن
if (PHP_SAPI === 'cli' && \getenv('TVN_PRELOAD_VERBOSE') === '1') {
    \fwrite(STDERR, \sprintf("[tavaan-preload] preloaded=%d failed=%d\n", $preloaded, $failed));
}

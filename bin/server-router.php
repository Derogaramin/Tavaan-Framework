<?php

/**
 * تَوان | Tavaan — Router script برای `php -S` (tvn serve).
 *
 * فایل‌های موجود در public/ مستقیم سرو می‌شوند؛
 * بقیهٔ درخواست‌ها به index.php می‌روند.
 */

declare(strict_types=1);

$path = \parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/../public' . \rawurldecode($path);

if ($path !== '/' && \is_file($file)) {
    // 🔒 فقط فایل‌های امن استاتیک
    $ext = \strtolower(\pathinfo($file, PATHINFO_EXTENSION));
    if (\in_array($ext, ['php'], true)) {
        // هیچ PHP دیگری جز index.php سرو نشود
        return false;
    }

    return false;   // خودِ php -S فایل استاتیک را سرو می‌کند
}

require __DIR__ . '/../public/index.php';

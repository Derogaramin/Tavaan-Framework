<?php

/**
 * تَوان | Tavaan — HTTP Entry Point
 * 🔒 تنها فایل PHP اجرایی در public/ (AC-STR-03).
 *
 * تا فاز M2 (موتور HTTP/Router) این Entry فقط بوت هسته را اثبات می‌کند.
 */

declare(strict_types=1);

use Tavaan\Core\Application\ApplicationBuilder;

require \dirname(__DIR__) . '/core/bootstrap.php';

try {
    $app = ApplicationBuilder::create(TVN_BASE_PATH)->boot();

    // M2: HttpKernel->handle(Request::capture())->send()
    \header('Content-Type: text/html; charset=utf-8');
    \header('X-Tavaan-Version: ' . TVN_VERSION);

    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
        . '<title>تَوان | Tavaan</title></head><body style="font-family:Tahoma">'
        . '<h1>سلام، تَوان! 👋</h1>'
        . '<p>هستهٔ فریمورک بوت شد (فاز M1). موتور HTTP در فاز M2 فعال می‌شود.</p>'
        . '<p style="color:#888">boot: ' . \sprintf('%.2f', $app->bootTimeMs()) . ' ms — env: '
        . \htmlspecialchars($app->environment(), ENT_QUOTES) . '</p>'
        . '</body></html>';

    $app->terminate();
} catch (\Throwable $e) {
    if (isset($app) && $app->bound(\Tavaan\Engine\Error\ExceptionHandler::class)) {
        $app->make(\Tavaan\Engine\Error\ExceptionHandler::class)->handle($e)->send();
    } else {
        \http_response_code(500);
        \fwrite(STDERR, '[' . $e::class . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
        echo 'Server Error (ref: bootstrap)';
    }
    exit(1);
}

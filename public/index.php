<?php

/**
 * تَوان | Tavaan — HTTP Entry Point (DOC-025 P1)
 * 🔒 تنها فایل PHP اجرایی در public/ (AC-STR-03).
 */

declare(strict_types=1);

use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Engine\Http\HttpKernel;
use Tavaan\Engine\Http\Request;

// ۱) bootstrap (تنها require دستی)
require \dirname(__DIR__) . '/core/bootstrap.php';

// ۲) ساخت اپلیکیشن
$app = ApplicationBuilder::create(TVN_BASE_PATH)->boot();

// ۳) اجرای درخواست
try {
    /** @var HttpKernel $kernel */
    $kernel = $app->make(HttpKernel::class);
    $request = Request::capture();

    $response = $kernel->handle($request);
    $kernel->sendAndTerminate($request, $response);
} catch (\Throwable $e) {
    // خطای پیش از Kernel (بوت) — بدون نشت جزئیات در production
    if ($app->bound(\Tavaan\Engine\Error\ExceptionHandler::class)) {
        $app->make(\Tavaan\Engine\Error\ExceptionHandler::class)->handle($e)->send();
    } else {
        if (!\headers_sent()) {
            \http_response_code(500);
        }
        echo 'Server Error';
        \fwrite(STDERR, '[' . $e::class . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    }
    $app->terminate();
    exit(1);
}

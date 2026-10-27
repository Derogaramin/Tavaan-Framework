<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — سرور سنجهٔ هاست اشتراکی (DOC-134 §5.4)
 *
 * معادل public/index.php است، اما معیارهای سمت‌سرور هر درخواست را به یک
 * فایل JSON-lines می‌نویسد. چون `php -S` یک فرآیند پایدار است، OPcache بین
 * درخواست‌ها «گرم» می‌ماند — دقیقاً مثل php-fpm/LiteSpeed در هاست اشتراکی.
 *
 * اجرا:
 *   php -c <profile.ini> -S 127.0.0.1:PORT tools/scripts/bench-server.php
 * مسیرها:
 *   /__measure/time|view|db  → اجرای سناریو + ثبت معیار
 *   هر مسیر دیگر              → هندل عادی توسط HttpKernel
 */

use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Engine\Http\HttpKernel;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

$base = \dirname(__DIR__, 2);
\define('TVN_BASE_PATH', $base);

$logFile = \getenv('TVN_BENCH_LOG') ?: ($base . '/storage/temp/bench-server.log');
$mapUri = [
    '/__measure/time' => '/time',
    '/__measure/view' => '/',
    '/__measure/db'   => '/blog',
];

$reqUri = \parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// ── مسیرهای اندازه‌گیری ──────────────────────────────────────────────────────
if (isset($mapUri[$reqUri])) {
    $realUri = $mapUri[$reqUri];
    $warm = (bool) (\getenv('TVN_BENCH_WARM') === '1');

    $_SERVER['REQUEST_URI'] = $realUri;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'shared-host.local';
    $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    $_GET = $_POST = $_COOKIE = $_FILES = [];

    $oc0 = \function_exists('opcache_get_status') ? (opcache_get_status(false) ?: []) : [];
    $t0 = \hrtime(true);

    require $base . '/core/bootstrap.php';
    $app = ApplicationBuilder::create(TVN_BASE_PATH)->boot();
    /** @var HttpKernel $kernel */
    $kernel = $app->make(HttpKernel::class);
    $request = Request::capture();
    $tKernel0 = \hrtime(true);
    $response = $kernel->handle($request);
    $kernelMs = (\hrtime(true) - $tKernel0) / 1e6;
    $totalMs = (\hrtime(true) - $t0) / 1e6;

    $oc1 = \function_exists('opcache_get_status') ? (opcache_get_status(false) ?: []) : [];
    $rec = [
        'ts'          => \microtime(true),
        'uri'         => $realUri,
        'status'      => $response->statusCode(),
        'body_bytes'  => \strlen($response->body()),
        'kernel_ms'   => \round($kernelMs, 2),
        'total_ms'    => \round($totalMs, 2),
        'peak_alloc_mb' => \round(\memory_get_peak_usage(true) / 1048576, 2),
        'peak_used_mb'  => \round(\memory_get_peak_usage(false) / 1048576, 2),
        'files'       => \count(\get_included_files()),
        'classes'     => \count(\get_declared_classes()),
        'ini'         => (string) (\php_ini_loaded_file() ?: '(default)'),
        'memory_limit'=> (string) \ini_get('memory_limit'),
        'opcache'     => [
            'used_mb'      => \round(($oc1['memory_usage']['used_memory'] ?? 0) / 1048576, 2),
            'free_mb'      => \round(($oc1['memory_usage']['free_memory'] ?? 0) / 1048576, 2),
            'scripts'      => $oc1['opcache_statistics']['num_cached_scripts'] ?? 0,
            'hits'         => $oc1['opcache_statistics']['hits'] ?? 0,
            'misses'       => $oc1['opcache_statistics']['misses'] ?? 0,
            'used_delta_mb'=> \round((($oc1['memory_usage']['used_memory'] ?? 0) - ($oc0['memory_usage']['used_memory'] ?? 0)) / 1048576, 3),
        ],
    ];
    \file_put_contents($logFile, \json_encode($rec, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

    $json = \json_encode($rec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    \header('Content-Type: application/json; charset=UTF-8');
    \http_response_code(200);
    echo $json;
    exit(0);
}

// ── هندل عادی (برای load-test روی مسیرهای واقعی) ────────────────────────────
require $base . '/core/bootstrap.php';
$app = ApplicationBuilder::create(TVN_BASE_PATH)->boot();
/** @var HttpKernel $kernel */
$kernel = $app->make(HttpKernel::class);
$request = Request::capture();
$response = $kernel->handle($request);
$kernel->sendAndTerminate($request, $response);

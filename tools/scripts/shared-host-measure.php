<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — سنجهٔ منابع هاست اشتراکی (DOC-134)
 *
 * یک درخواست HTTP واقعی را «درون‌فرآیندی» اجرا می‌کند و منابع مصرفی را
 * اندازه می‌گیرد: حافظهٔ اوج، زمان، تعداد فایل include‌شده، کلاس‌های
 * declare‌شده و footprint در OPcache.
 *
 * استفاده (یک بار در هر پروفایل ini):
 *   php -c examples/hosting/php-ideal.ini tools/scripts/shared-host-measure.php <scenario>
 *
 * سناریوها:
 *   boot    — فقط bootstrap (بدون اپلیکیشن)
 *   time    — GET /time  (JSON closure — سبک‌ترین مسیر کامل)
 *   view    — GET /      (welcome — موتور View + کامپایل قالب)
 *   db      — GET /blog  (ماژول blog → SQLite/MySQL + view + session)
 *   notfound— GET /no-such-page (مسیر 404)
 *
 * خروجی: یک خط JSON (برای تجمیع توسط harness).
 */

// مسیر base «پیش از» bootstrap تعریف می‌شود (bootstrap به آن احترام می‌گذارد)
\define('TVN_BASE_PATH', \dirname(__DIR__, 2));

$scenario = $argv[1] ?? 'time';
$routes = [
    'time'     => '/time',
    'view'     => '/',
    'db'       => '/blog',
    'notfound' => '/no-such-page-xyz',
];

$mem0 = \memory_get_usage(true);      // مصرف خالص PHP (بدون فریمورک)
$t0 = \hrtime(true);

require TVN_BASE_PATH . '/core/bootstrap.php';

$tBoot = (\hrtime(true) - $t0) / 1e6;

$out = [
    'scenario'      => $scenario,
    'ini'           => (string) \php_ini_loaded_file() ?: '(default)',
    'php'           => PHP_VERSION,
    'sapi'          => PHP_SAPI,
    'memory_limit'  => (string) \ini_get('memory_limit'),
    'opcache_on'    => \function_exists('opcache_get_status'),
    'php_baseline'  => $mem0,
];

// ── سناریوی boot-only ───────────────────────────────────────────────────────
if ($scenario === 'boot') {
    $out += [
        'boot_ms'       => \round($tBoot, 2),
        'total_ms'      => \round($tBoot, 2),
        'peak_alloc_mb' => \round(\memory_get_peak_usage(true) / 1048576, 2),
        'peak_used_mb'  => \round(\memory_get_peak_usage(false) / 1048576, 2),
        'files'         => \count(\get_included_files()),
        'classes'       => \count(\get_declared_classes()) + \count(\get_declared_interfaces()),
    ];
    echo \json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
}

// ── شبیه‌سازی $_SERVER یک درخواست GET اشتراکی (LiteSpeed/Apache) ────────────
$uri = $routes[$scenario] ?? '/time';
$_SERVER = \array_diff_key($_SERVER, ['argv' => 1, 'argc' => 1]);
$_SERVER += [
    'REQUEST_METHOD'    => 'GET',
    'REQUEST_URI'       => $uri,
    'SCRIPT_NAME'       => '/index.php',
    'SCRIPT_FILENAME'   => TVN_BASE_PATH . '/public/index.php',
    'DOCUMENT_ROOT'     => TVN_BASE_PATH . '/public',
    'SERVER_PROTOCOL'   => 'HTTP/1.1',
    'SERVER_SOFTWARE'   => 'LiteSpeed',
    'HTTP_HOST'         => 'shared-host.local',
    'HTTP_USER_AGENT'   => 'Mozilla/5.0 (TavaanSharedHostMeasure)',
    'HTTP_ACCEPT'       => 'text/html,application/json',
    'REMOTE_ADDR'       => '127.0.0.1',
    'SERVER_NAME'       => 'shared-host.local',
    'SERVER_PORT'       => '443',
    'HTTPS'             => 'on',
    'QUERY_STRING'      => '',
    'CONTENT_LENGTH'    => '',
    'CONTENT_TYPE'      => '',
];
$_GET = $_POST = $_COOKIE = $_FILES = [];

$oc0 = \function_exists('opcache_get_status') ? (opcache_get_status(false) ?: []) : [];

$tApp0 = \hrtime(true);
$app = \Tavaan\Core\Application\ApplicationBuilder::create(TVN_BASE_PATH)->boot();
$tApp = (\hrtime(true) - $tApp0) / 1e6;

$tMk = \hrtime(true);
/** @var \Tavaan\Engine\Http\HttpKernel $kernel */
$kernel = $app->make(\Tavaan\Engine\Http\HttpKernel::class);
$tMake = (\hrtime(true) - $tMk) / 1e6;
$tMake0 = \hrtime(true);
$request = \Tavaan\Engine\Http\Request::capture();
$tCapture = (\hrtime(true) - $tMake0) / 1e6;

$t1 = \hrtime(true);
$response = $kernel->handle($request);
$tKernel = (\hrtime(true) - $t1) / 1e6;

$oc1 = \function_exists('opcache_get_status') ? (opcache_get_status(false) ?: []) : [];

$out += [
    'uri'           => $uri,
    'status'        => $response->statusCode(),
    'body_bytes'    => \strlen($response->body()),
    'boot_ms'       => \round($tBoot, 2),
    'app_boot_ms'   => \round($tApp, 2),
    'make_kernel_ms'=> \round($tMake, 2),
    'capture_ms'    => \round($tCapture, 2),
    'kernel_ms'     => \round($tKernel, 2),
    'total_ms'      => \round((\hrtime(true) - $t0) / 1e6, 2),
    'peak_alloc_mb' => \round(\memory_get_peak_usage(true) / 1048576, 2),
    'peak_used_mb'  => \round(\memory_get_peak_usage(false) / 1048576, 2),
    'files'         => \count(\get_included_files()),
    'classes'       => \count(\get_declared_classes()) + \count(\get_declared_interfaces()),
];

if ($oc0 !== [] && $oc1 !== []) {
    $mem0oc = $oc0['memory_usage'] ?? [];
    $mem1oc = $oc1['memory_usage'] ?? [];
    $out['opcache_used_delta_mb'] = \round((($mem1oc['used_memory'] ?? 0) - ($mem0oc['used_memory'] ?? 0)) / 1048576, 2);
    $out['opcache_scripts_delta'] = ($oc1['opcache_statistics']['num_cached_scripts'] ?? 0) - ($oc0['opcache_statistics']['num_cached_scripts'] ?? 0);
    $out['opcache_keys_delta']    = ($oc1['opcache_statistics']['num_cached_keys'] ?? 0) - ($oc0['opcache_statistics']['num_cached_keys'] ?? 0);
}

echo \json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit(0);

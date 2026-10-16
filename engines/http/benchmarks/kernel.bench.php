<?php

/**
 * تَوان | Tavaan — Hello World Overhead Benchmark (AC-HTTP-02)
 *
 * سربار هر درخواست (kernel->handle بدون boot) باید ≤ ۳ms باشد.
 *
 * اجرا: php engines/http/benchmarks/kernel.bench.php
 */

declare(strict_types=1);

require \dirname(__DIR__, 3) . '/core/bootstrap.php';

use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Engine\Http\HttpKernel;
use Tavaan\Engine\Http\Request;

$_SERVER['TVN_APP_ENV'] = 'testing';

$app = ApplicationBuilder::create(TVN_BASE_PATH)->forTesting()->boot();
$app->make('config')->set('session.driver', 'array');
$app->make('config')->set('log.default', 'array');

$bootMs = $app->bootTimeMs();

/** @var HttpKernel $kernel */
$kernel = $app->make(HttpKernel::class);
/** @var \Tavaan\Engine\Http\Routing\Router $router */
$router = $app->make('router');

$router->get('/bench/hello', static fn (): string => 'Hello, Tavaan!');

$request = Request::create('http://localhost/bench/hello');

// warmup
for ($i = 0; $i < 20; $i++) {
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
}

$iterations = 200;
$start = \hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
}
$perRequestMs = (\hrtime(true) - $start) / 1e6 / $iterations;

// DOC-112: PERF-BOOT-01 (warm با opcache) ≤ ۸ms — PERF-BOOT-02 (cold بدون opcache) ≤ ۲۵ms
// در CLI با opcache.file_cache، اجرای دوم به بعد warm است (کش bytecode روی دیسک).
$opcacheActive = \function_exists('opcache_get_status')
    && ((bool) (@\opcache_get_status(false)['opcache_enabled'] ?? false)
        || (string) \ini_get('opcache.file_cache') !== '');
$bootTarget = $opcacheActive ? 8.0 : 25.0;
$bootLabel = $opcacheActive ? 'warm opcache' : 'cold — بدون opcache';

\printf("boot (یک‌بار، با همهٔ middlewareها — %s) %9.2f ms (target ≤ %.0f ms) %s\n", $bootLabel, $bootMs, $bootTarget, $bootMs <= $bootTarget ? '✅' : '⚠️');
\printf("per-request overhead (hello world)         %9.3f ms (target ≤ 3 ms) %s\n", $perRequestMs, $perRequestMs <= 3 ? '✅' : '❌');
\printf("body check                                   %s\n", $response->body() === 'Hello, Tavaan!' ? '✓' : '✗ ' . $response->body());
\printf("RPS theoretical (single process)           %9.0f\n", 1000 / \max(0.001, $perRequestMs));

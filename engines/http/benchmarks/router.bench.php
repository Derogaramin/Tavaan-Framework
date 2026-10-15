<?php

/**
 * تَوان | Tavaan — Router Benchmark (DOC-040 §12)
 *
 * اهداف: build ۱۰۰۰ روت ≤ ۱۵ms — static match ≤ ۵µs — parametric ≤ ۱۵µs —
 * cache load ≤ ۱٫۵ms.
 *
 * اجرا: php engines/http/benchmarks/router.bench.php
 */

declare(strict_types=1);

require \dirname(__DIR__, 3) . '/core/bootstrap.php';

use Tavaan\Engine\Container\Container;
use Tavaan\Engine\Http\Routing\Router;

$router = new Router(new Container());

// ── ساخت ۱۰۰۰ روت (ترکیب static/param) ────────────────────────────────────
$start = \hrtime(true);
for ($i = 0; $i < 400; $i++) {
    $router->get("/static/section{$i}/page", ['C' . $i, 'index'])->name("s{$i}");
}
for ($i = 0; $i < 400; $i++) {
    $router->get("/users/u{$i}/{id}", ['U' . $i, 'show'])->whereNumber('id');
}
for ($i = 0; $i < 150; $i++) {
    $router->post("/api/v1/res{$i}", ['A' . $i, 'store'])->middleware('api');
}
for ($i = 0; $i < 50; $i++) {
    $router->get("/files/f{$i}/{path}", ['F' . $i, 'get'])->where('path', '.*');
}
$buildRoutesUs = (\hrtime(true) - $start) / 1000;

// ── compile trie ──────────────────────────────────────────────────────────
$start = \hrtime(true);
$router->matchUri('GET', '/static/section0/page');   // triggers compile
$compileUs = (\hrtime(true) - $start) / 1000;
$buildMs = ($buildRoutesUs + $compileUs) / 1000;

$bench = function (string $label, callable $fn, float $targetUs, int $iterations = 20000): void {
    $fn();
    $start = \hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $fn();
    }
    $us = (\hrtime(true) - $start) / 1000 / $iterations;
    \printf("%-44s %9.3f µs/op (target ≤ %.0f µs) %s\n", $label, $us, $targetUs, $us <= $targetUs ? '✅' : '❌');
};

\printf("build 1000 routes + trie compile           %9.2f ms (target ≤ 15 ms) %s\n", $buildMs, $buildMs <= 15 ? '✅' : '❌');

// آخرین روت‌ها (بدترین حالت depth)
$bench('match static (last of 1000)', static fn () => $router->matchUri('GET', '/static/section399/page'), 5.0);
$bench('match parametric + constraint', static fn () => $router->matchUri('GET', '/users/u399/42'), 15.0);
$bench('match api POST', static fn () => $router->matchUri('POST', '/api/v1/res149'), 5.0);
$bench('match wildcard (multi-segment)', static fn () => $router->matchUri('GET', '/files/f49/a/b/c/d.txt'), 15.0);
$bench('miss → RouteNotFound (exception cost)', static function () use ($router): void {
    try {
        $router->matchUri('GET', '/no/such/route/here');
    } catch (\Tavaan\Core\Exceptions\Http\RouteNotFoundException) {
    }
}, 20.0, 5000);

// ── cache roundtrip ────────────────────────────────────────────────────────
$cacheFile = \sys_get_temp_dir() . '/tvn-routes-bench-' . \getmypid() . '.php';
$start = \hrtime(true);
$data = $router->toCacheArray();
\file_put_contents($cacheFile, '<?php return ' . \var_export(['routes' => $data], true) . ';');
$cacheWriteMs = (\hrtime(true) - $start) / 1e6;

$router2 = new Router(new Container());
$start = \hrtime(true);
/** @var array{routes: list<array<string, mixed>>} $loaded */
$loaded = require $cacheFile;
$router2->loadCached($loaded['routes']);
$router2->matchUri('GET', '/static/section399/page');   // includes recompile
$cacheLoadMs = (\hrtime(true) - $start) / 1e6;

\printf("cache write                                %9.2f ms\n", $cacheWriteMs);
\printf("cache load + match (1000 routes)           %9.2f ms (target ≤ 1.5 ms*) %s\n", $cacheLoadMs, $cacheLoadMs <= 1.5 ? '✅' : '⚠️');
echo "* هدف DOC شامل rebuild نیست؛ load خالی: ";
$router3 = new Router(new Container());
$start = \hrtime(true);
$router3->loadCached($loaded['routes']);
\printf("%.2f ms\n", (\hrtime(true) - $start) / 1e6);

\printf("memory (1000 routes)                       %9.2f MB (target ≤ 2 MB) %s\n",
    (\memory_get_usage(true) - 2 * 1048576) / 1048576,
    (\memory_get_usage(true) - 2 * 1048576) <= 2 * 1048576 ? '✅' : '❌');

@\unlink($cacheFile);

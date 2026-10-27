<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — پروفایلر بوت‌استرپرها (DOC-134 §5.2)
 * هر Bootstrapper را جدا زمان‌سنجی می‌کند تا گلوگاه app_boot پیدا شود.
 */

\define('TVN_BASE_PATH', \dirname(__DIR__, 2));

$t0 = \hrtime(true);
require TVN_BASE_PATH . '/core/bootstrap.php';
$boot = (\hrtime(true) - $t0) / 1e6;

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/time';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['HTTP_HOST'] = 'x.local';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$app = new \Tavaan\Core\Application\Application(TVN_BASE_PATH);

$bootstrappers = \Tavaan\Core\Application\ApplicationBuilder::defaultBootstrappers();
$mem0 = \memory_get_usage(true);
$rows = [];
$total = 0.0;
foreach ($bootstrappers as $cls) {
    /** @var \Tavaan\Core\Bootstrap\BootstrapperInterface $b */
    $b = new $cls();
    $t = \hrtime(true);
    $m = \memory_get_usage(true);
    $b->bootstrap($app);
    $dt = (\hrtime(true) - $t) / 1e6;
    $dm = \memory_get_usage(true) - $m;
    $total += $dt;
    $rows[] = [
        'bootstrapper' => (new \ReflectionClass($cls))->getShortName(),
        'ms' => \round($dt, 2),
        'mem_mb' => \round($dm / 1048576, 2),
        'files' => \count(\get_included_files()),
        'classes' => \count(\get_declared_classes()),
    ];
}

echo \json_encode([
    'bootstrap_php_ms' => \round($boot, 2),
    'bootstrappers_total_ms' => \round($total, 2),
    'peak_alloc_mb' => \round(\memory_get_peak_usage(true) / 1048576, 2),
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;

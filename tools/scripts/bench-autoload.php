<?php

/**
 * AC-BOOT-04 — بنچمارک «زمان Resolve» (یافتن مسیر فایل) با ClassMap در برابر
 * Prefix/Wildcard (شامل statهای is_file). کامپایل/include حذف شده زیرا در PROD
 * با Opcache تقریباً رایگان است و کش ClassMap فقط lookup را حذف می‌کند.
 *
 * اجرا: php tools/scripts/bench-autoload.php
 */

declare(strict_types=1);

$base = \dirname(__DIR__, 2);
require $base . '/core/bootstrap.php';

use Tavaan\Core\Autoloading\Autoloader;

$dumper = Autoloader::create($base);
$dumper->addNamespace('Tavaan\\Engine\\', 'engines/*/src/');
$dumper->addNamespace('Tavaan\\Core\\', 'core/');
$dumper->addNamespace('Tavaan\\Support\\', 'core/Support/');
$map = $dumper->dumpClassMap(null, ['tests/*']);

$classes = \array_keys($map);
\sort($classes);
\shuffle($classes);

// سناریوی A: prefix/wildcard
$plain = Autoloader::create($base);
$plain->addNamespace('Tavaan\\Engine\\', 'engines/*/src/');
$plain->addNamespace('Tavaan\\Core\\', 'core/');
$plain->addNamespace('Tavaan\\Support\\', 'core/Support/');

// سناریوی B: classmap
$cached = Autoloader::create($base);
$cached->addClassMap($map, authoritative: true);

$resolve = static function (Autoloader $loader) use ($classes): float {
    $start = \hrtime(true);
    foreach ($classes as $class) {
        $loader->findFile($class);
    }

    return (\hrtime(true) - $start) / 1000;   // µs
};

// warmup + پاکسازی stat cache بین اندازه‌گیری‌ها
$resolve($plain);
$resolve($cached);

$rounds = 50;
$tPlain = [];
$tCached = [];
for ($r = 0; $r < $rounds; $r++) {
    \clearstatcache();
    $tPlain[] = $resolve($plain);
    \clearstatcache();
    $tCached[] = $resolve($cached);
}
\sort($tPlain);
\sort($tCached);

$medPlain = $tPlain[(int) ($rounds / 2)];
$medCached = $tCached[(int) ($rounds / 2)];
$improvement = (1 - $medCached / $medPlain) * 100;

\printf("classes          : %d\n", \count($classes));
\printf("prefix/wildcard  : %8.1f µs/round (median)\n", $medPlain);
\printf("classmap         : %8.1f µs/round\n", $medCached);
\printf("per class        : %8.2f µs → %8.2f µs\n", $medPlain / \count($classes), $medCached / \count($classes));
\printf("improvement      : %8.1f%%  %s (target ≥ 70%%)\n", $improvement, $improvement >= 70 ? '✅' : '❌');

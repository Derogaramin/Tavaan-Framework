<?php

/**
 * زیرفرایند بنچمارک Autoload —include واقعی کلاس‌ها در فرایند تازه.
 *
 * کاربرد داخلی: php bench-autoload-child.php <prefix|classmap> <basePath>
 */

declare(strict_types=1);

$mode = $argv[1] ?? 'prefix';
$base = \rtrim($argv[2] ?? \dirname(__DIR__, 2), '/');

// زنجیرهٔ حداقلی require (بدون bootstrap کامل)
require $base . '/core/Error/RefCodeGenerator.php';
require $base . '/core/Error/LogMasker.php';
require $base . '/core/Exceptions/RenderableException.php';
require $base . '/core/Exceptions/ContextualException.php';
require $base . '/core/Exceptions/ReportableException.php';
require $base . '/core/Exceptions/TavaanException.php';
require $base . '/core/Autoloading/AutoloadException.php';
require $base . '/core/Autoloading/AutoloaderStats.php';
require $base . '/core/Autoloading/Autoloader.php';
require $base . '/core/Autoloading/ClassMapGenerator.php';

use Tavaan\Core\Autoloading\Autoloader;

$classes = require $base . '/storage/cache/bench-classes.php';

$autoloader = Autoloader::create($base);
if ($mode === 'classmap') {
    $autoloader->addClassMap(require $base . '/storage/cache/classmap-bench.php');
} else {
    $autoloader->addNamespace('Tavaan\\Engine\\', 'engines/*/src/');
    $autoloader->addNamespace('Tavaan\\Core\\', 'core/');
    $autoloader->addNamespace('Tavaan\\Support\\', 'core/Support/');
}
$autoloader->register();

$start = \hrtime(true);
foreach ($classes as $class) {
    if (!\class_exists($class) && !\interface_exists($class) && !\enum_exists($class)) {
        \fwrite(STDERR, 'missing: ' . $class . PHP_EOL);
    }
}
$us = (\hrtime(true) - $start) / 1000;

echo (string) (int) $us;

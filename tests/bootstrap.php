<?php

/**
 * تَوان | Tavaan — Test Bootstrap
 * بوت تست بدون phpunit autoload (DOC-080 tests/bootstrap.php).
 */

declare(strict_types=1);

use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Core\Application\RuntimeMode;

require \dirname(__DIR__) . '/core/bootstrap.php';

// حالت تست: بدون cache، env=testing
$_SERVER['TVN_APP_ENV'] = 'testing';

/**
 * ساخت اپلیکیشن تست (هر Feature test یک نمونهٔ تازه می‌گیرد).
 */
function tavaan_test_app(): \Tavaan\Core\Application\Application
{
    $app = ApplicationBuilder::create(TVN_BASE_PATH)->forTesting()->boot();
    $app->setMode(RuntimeMode::Testing);

    return $app;
}

return true;

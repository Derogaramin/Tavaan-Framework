<?php

declare(strict_types=1);

/*
 * نگاشت namespaceها — DOC-027 §3 / DOC-082
 * این فایل در early bootstrap (قبل از helpers) خوانده می‌شود.
 */

return [
    'psr-4' => [
        'Tavaan\\Core\\'    => ['core/'],
        'Tavaan\\Support\\' => ['core/Support/'],
        'Tavaan\\Engine\\'  => ['engines/*/src/'],   // الگوی wildcard
        'Tavaan\\Testing\\' => ['engines/test/src/'],
        'App\\'             => ['app/src/'],
        'Modules\\'         => ['modules/*/src/'],    // الگوی wildcard
        'Kits\\'            => ['kits/*/src/'],
        'Plugins\\'         => ['plugins/*/src/'],
        'Database\\'        => ['database/'],
        'Tavaan\\Tests\\'    => ['tests/'],
        'Tests\\'           => ['tests/'],
    ],
    'classmap' => [],
    'files' => [
        'core/Support/helpers.php',
        'engines/config/src/helpers.php',
        'engines/i18n/src/helpers.php',
        'engines/http/src/helpers.php',
    ],
    'exclude' => [
        'tests/*', 'Tests/*', 'docs/*', 'examples/*', 'benchmarks/*',
    ],
    'cache' => [
        'enabled' => ($_SERVER['TVN_AUTOLOAD_CACHE'] ?? 'false') === 'true',
        'file'    => 'storage/cache/classmap.php',
    ],
    'locked_prefixes' => ['Tavaan\\Core\\', 'Tavaan\\Support\\', 'Tavaan\\Engine\\'],
];

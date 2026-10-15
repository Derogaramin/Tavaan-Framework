<?php

declare(strict_types=1);

/*
 * قوانین arch:check (مرزهای لایه‌ای — DOC-021/DOC-022).
 */

return [
    'rules' => [
        // core هرگز به engines/modules/app وابسته نباشد (به‌جز composition root)
        ['from' => 'Tavaan\\Core\\', 'deny' => ['Tavaan\\Engine\\', 'Modules\\', 'App\\'],
         'except' => ['Tavaan\\Core\\Bootstrap\\']],
        // موتور فقط به core و contractها
        ['from' => 'Tavaan\\Engine\\', 'deny' => ['Modules\\', 'App\\']],
        // ماژول به موتور دیگر فقط از طریق Contract/Event
        ['from' => 'Modules\\', 'deny' => ['Tavaan\\Engine\\'],
         'allow_namespace' => ['Tavaan\\Core\\Contracts\\', 'Tavaan\\Support\\']],
    ],
    'forbidden_functions' => [
        'eval', 'create_function', 'putenv', 'shell_exec', 'exec', 'system', 'passthru',
        'popen', 'proc_open', 'extract', 'compact', 'goto',
    ],
    'forbidden_patterns' => [
        '/\$\{[a-zA-Z_]/',            // F-02: ${var} interpolation
        '/\bprint_r\s*\(/',
        '/\bmt_rand\s*\(/',           // F-20
        '/\bunserialize\s*\(/',       // F-21
    ],
];

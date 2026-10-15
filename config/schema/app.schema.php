<?php

declare(strict_types=1);

/*
 * Schema اعتبارسنجی namespace «app» — DOC-032 §5.5
 */

return [
    'name'     => ['type' => 'string', 'max' => 100, 'required' => true],
    'env'      => ['type' => 'string', 'in' => ['local', 'development', 'testing', 'staging', 'production']],
    'debug'    => ['type' => 'bool'],
    'timezone' => ['type' => 'string'],
    'locale'   => ['type' => 'string', 'pattern' => '/^[a-z]{2}(_[A-Z]{2})?$/'],
    'key'      => ['type' => 'string', 'secret' => true, 'nullable' => true],
    'maintenance.driver' => ['type' => 'string', 'in' => ['file', 'cache', 'redis']],
];

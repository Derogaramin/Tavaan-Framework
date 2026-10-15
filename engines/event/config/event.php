<?php

declare(strict_types=1);

/*
 * پیکربندی موتور Event — DOC-033 §6
 */

return [
    'default_mode'     => env_string('TVN_EVENT_MODE', 'sync'),   // sync|outbox|queue
    'stop_on_error'    => false,
    'max_listeners'    => 500,
    'wildcard'         => true,
    'global_listeners' => false,
    'log_listeners'    => env_bool('TVN_EVENT_DEBUG', false),

    'discovery' => [
        'enabled' => true,
        'cache'   => storage_path('cache/events.php'),
        'paths'   => [base_path('modules'), base_path('app/src')],
        'suffix'  => 'EventServiceProvider',
    ],

    'outbox' => [
        'enabled'        => true,
        'table'          => 'tvn_outbox',
        'connection'     => null,
        'relay'          => 'inline',
        'batch_size'     => 100,
        'max_attempts'   => 10,
        'backoff'        => 'exponential',
        'base_seconds'   => 1,
        'jitter'         => 0.2,
        'retention_days' => 7,
        'publisher'      => 'local',
    ],

    'inbox' => [
        'enabled'  => true,
        'table'    => 'tvn_inbox',
        'ttl_days' => 30,
    ],

    'queue_listeners' => [
        'connection' => null,
        'queue'      => 'events',
    ],
];

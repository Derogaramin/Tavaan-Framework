<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Database
 * config/database.php — پیکربندی پیش‌فرض اتصالات (DOC-047 §3.1).
 */

return [
    'default' => env_string('TVN_DB_CONNECTION', 'sqlite'),

    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => env_string('TVN_DB_HOST', '127.0.0.1'),
            'port'        => env_int('TVN_DB_PORT', 3306),
            'database'    => env_string('TVN_DB_DATABASE', 'tavaan'),
            'username'    => env_string('TVN_DB_USERNAME', 'root'),
            'password'    => env_string('TVN_DB_PASSWORD', ''),
            'unix_socket' => env_string('TVN_DB_SOCKET', ''),
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_0900_ai_ci',
            'prefix'      => env_string('TVN_DB_PREFIX', ''),
            'strict'      => true,
            'engine'      => 'InnoDB',
            'options'     => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,   // 🔒 prepared واقعی سمت سرور
                \PDO::ATTR_STRINGIFY_FETCHES  => false,
                \PDO::ATTR_PERSISTENT         => false,
            ],
            'read'   => ['host' => []],
            'write'  => ['host' => env_string('TVN_DB_HOST', '127.0.0.1')],
            'sticky' => true,
        ],

        'pgsql' => [
            'driver'    => 'pgsql',
            'host'      => env_string('TVN_DB_HOST', '127.0.0.1'),
            'port'      => env_int('TVN_DB_PORT', 5432),
            'database'  => env_string('TVN_DB_DATABASE', 'tavaan'),
            'username'  => env_string('TVN_DB_USERNAME', 'postgres'),
            'password'  => env_string('TVN_DB_PASSWORD', ''),
            'charset'   => 'utf8',
            'prefix'    => '',
            'schema'    => 'public',
            'sslmode'   => 'prefer',
            'options'   => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::ATTR_STRINGIFY_FETCHES  => false,
            ],
            'sticky' => true,
        ],

        'sqlite' => [
            'driver'                  => 'sqlite',
            'database'                => env_string('TVN_DB_DATABASE', storage_path('database/tavaan.sqlite')),
            'prefix'                  => '',
            'foreign_key_constraints' => true,
            'busy_timeout'            => 5000,
            'options'                 => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
            'sticky' => true,
        ],

        'sqlite_memory' => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
            'options'  => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
        ],
    ],

    'pool' => ['enabled' => false, 'min' => 1, 'max' => 10],

    'query_log' => [
        'enabled'        => env_bool('TVN_DB_QUERY_LOG', false),
        'slow_threshold' => 100,   // ms
        'max_entries'    => 500,
    ],

    'n_plus_one_detection' => env_bool('TVN_DB_N1_DETECT', false),
    'n_plus_one_threshold' => 5,

    'pagination' => [
        'max_per_page' => 100,   // 🔒 DoS با per_page بزرگ
    ],
];

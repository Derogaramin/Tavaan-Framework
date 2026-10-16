<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Search
 * config/search.php — پیکربندی جست‌وجو (DOC-052 §8).
 *
 * پیش‌فرض `native` است (ایندکس معکوس خودنوشته، بدون DB، فارسی‌آگاه)؛
 * درایورهای SQL در تولید توصیه می‌شوند.
 */

return [
    // native|sqlite|mysql|pgsql|meilisearch|typesense
    'default' => env_string('TVN_SEARCH_DRIVER', 'native'),

    // ایندکس خودکار مدل‌های Searchable از رویدادهای model.saved/deleted
    'auto_index' => env_bool('TVN_SEARCH_AUTO_INDEX', true),

    'indexes' => [
        // 'products' => [
        //     'driver' => null,                  // null = پیش‌فرض
        //     'model' => \App\Models\Product::class,
        //     'fields' => ['title', 'summary', 'description'],
        //     'searchable' => ['title^3', 'summary', 'description'],
        //     'filterable' => ['category_id', 'price', 'status'],
        //     'sortable' => ['price', 'created_at'],
        //     'numeric' => ['price'],            // ستون‌های عددی (CAST در sort/filter)
        // ],
    ],

    'native' => [
        'path' => storage_path('search'),
        'persist' => env_bool('TVN_SEARCH_NATIVE_PERSIST', true),
    ],

    'sqlite' => [
        'connection' => null,        // null = اتصال پیش‌فرض database
        'prefix' => 'tvn_fts_',
    ],

    'mysql' => [
        'connection' => null,
        'prefix' => 'tvn_fts_',
        'ngram' => env_bool('TVN_SEARCH_NGRAM', false),   // WITH PARSER ngram (CJK)
    ],

    'pgsql' => [
        'connection' => null,
        'prefix' => 'tvn_fts_',
    ],

    'meilisearch' => [
        'host' => env_string('TVN_MEILI_HOST', ''),
        'key' => env_string('TVN_MEILI_KEY', ''),
    ],

    'typesense' => [
        'host' => env_string('TVN_TYPESENSE_HOST', ''),
        'key' => env_string('TVN_TYPESENSE_KEY', ''),
    ],

    // زبان ایست‌واژه‌ها (فعلاً fa داخلی است)
    'stop_words' => 'fa',
    'min_token_length' => 2,

    // 🔒 محدودیت‌های DoS
    'max_query_length' => 500,
    'max_tokens' => 64,
    'max_facets' => 50,
    'max_per_page' => 100,
];

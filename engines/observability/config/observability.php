<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Observability
 * config/observability.php — پیکربندی رصدپذیری (DOC-067 §6).
 */

return [
    'metrics' => [
        'enabled' => env_bool('TVN_METRICS', true),
        'endpoint' => '/metrics',
        'prefix' => env_string('TVN_METRICS_PREFIX', 'tavaan'),
        // پایستگی بین درخواست‌ها در cache (برای /metrics تجمعی) — opt-in
        'persist' => env_bool('TVN_METRICS_PERSIST', false),
        // ثبت خودکار سنجه‌های HTTP با middleware سرتاسری
        'http_middleware' => env_bool('TVN_METRICS_HTTP', true),
    ],

    'tracing' => [
        'enabled' => env_bool('TVN_TRACING', false),
        // array|log|otlp|jaeger|none
        'exporter' => env_string('TVN_TRACE_EXPORTER', 'log'),
        'sample_rate' => env_float('TVN_TRACE_SAMPLE', 1.0),
        'service' => env_string('TVN_SERVICE_NAME', 'tavaan'),
        'otlp_endpoint' => env_string('OTEL_EXPORTER_OTLP_ENDPOINT', ''),
        'jaeger_host' => env_string('TVN_JAEGER_HOST', '127.0.0.1'),
        'jaeger_port' => env_int('TVN_JAEGER_PORT', 6831),
    ],

    'health' => [
        'endpoint' => '/health',
        'checks' => ['database', 'cache', 'queue', 'disk'],
        'queue_max' => 10000,
        'disk_min_bytes' => 1073741824,   // ۱ گیگابایت
    ],

    'debug_bar' => [
        'enabled' => env_bool('TVN_DEBUG_BAR', false),
        'max_events' => 500,
        'max_queries' => 100,
    ],
];

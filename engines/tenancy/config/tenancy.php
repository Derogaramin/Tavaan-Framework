<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Tenancy
 * config/tenancy.php — پیکربندی چنداجاره‌ای (DOC-082E §10 / DOC-028).
 */

return [
    'enabled' => env_bool('TVN_TENANCY', false),

    // shared|database|schema
    'strategy' => env_string('TVN_TENANCY_STRATEGY', 'shared'),

    // اتصال DB مرکزی (خالی = اتصال پیش‌فرض database)
    'central_connection' => env_string('TVN_TENANCY_CENTRAL', ''),

    // قالب اتصال tenant در استراتژی B (خالی = کپی از اتصال مرکزی)
    'tenant_template' => '',

    // مسیر فایل‌های sqlite در استراتژی B
    'sqlite_path' => storage_path('tenants'),

    // پیشوند schema در استراتژی C
    'schema_prefix' => 'tenant_',

    // کلاس مدل tenant (باید از Tavaan\Engine\Tenancy\Tenant ارث ببرد)
    'tenant_model' => \Tavaan\Engine\Tenancy\Tenant::class,

    // جدول ثبت tenantها
    'table' => 'tvn_tenants',

    // زنجیرهٔ resolverها به ترتیب اولویت
    'resolver' => ['domain', 'header', 'path', 'token'],

    // تنظیمات resolverها
    'resolvers' => [
        'domain_suffix' => env_string('TVN_TENANT_DOMAIN', ''),   // مثلاً .example.com
        'header' => 'X-Tenant-Id',
        'path_prefix' => 't',
        'query_param' => 'tenant',
        'query_dev_only' => true,
        'token_claim' => 'tenant_id',
        'token_decode_unverified' => false,
        'default_tenant' => env_string('TVN_TENANT_DEFAULT', ''),
    ],

    'tenant_column' => 'tenant_id',

    // جداول سراسری (بدون scope)
    'ignore_tables' => ['tvn_tenants', 'tvn_migrations', 'users'],

    'quota' => [
        'enabled' => true,
        'defaults' => [],
    ],

    // جداکردن کلیدهای cache با prefix tenant (tvn:t{id}:)
    'isolate_cache' => true,

    // میان‌بر مسیر فایل tenant: storage/tenants/{identifier}/
    'storage_path' => storage_path('tenants'),
];

<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — RBAC
 * config/rbac.php — پیکربندی کنترل دسترسی (DOC-057 §8).
 */

return [
    'driver' => 'database',                 // database|array

    'tables' => [
        'permissions'      => 'tvn_permissions',
        'roles'            => 'tvn_roles',
        'role_user'        => 'tvn_role_user',
        'permission_role'  => 'tvn_permission_role',
        'delegations'      => 'tvn_delegations',
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => 'tvn:rbac:',
    ],

    'hierarchy' => [
        'enabled' => true,
        'max_depth' => 5,
    ],

    'super_admin' => [
        'role' => 'super-admin',
        'bypasses_gate' => true,
    ],

    // نگاشت اعلامی Policyها — ماژول‌ها با modules/<x>/config/rbac.php ادغام
    // می‌کنند (M9). Gate در اولین مصرف ساخته می‌شود → هیچ resolve زودهنگامِ
    // اتصال DB در boot (مشکل اتصال کهنه در تست‌های RefreshDatabase).
    // قالب: '<Model FQCN>' => '<Policy FQCN>'
    'policies' => [],

    'wildcard' => [
        'enabled' => true,
        'separator' => '*',
    ],

    'delegation' => [
        'enabled' => true,
        'max_duration_days' => 90,
    ],

    'default_role' => env_string('TVN_DEFAULT_ROLE', 'user'),
];

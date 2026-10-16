<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — ORM
 * config/orm.php — پیکربندی ORM (DOC-050).
 */

return [
    // 🔒 Mass Assignment: در dev سخت‌گیر (throw)؛ در seed با Model::unguard()
    'strict_mass_assignment' => env_bool('TVN_ORM_STRICT_MASS', true),

    // هشدار lazy loading در حلقه (N+1) — نیازمند database.n_plus_one_detection
    'prevent_lazy_loading' => env_bool('TVN_ORM_PREVENT_LAZY', false),

    // نگاشت alias ↔ class برای روابط چندریختی (پایداری در برابر refactor)
    'morph_map' => [
        // 'product' => \Modules\Shop\Models\Product::class,
    ],

    // پیش‌فرض‌های مدل
    'defaults' => [
        'key_type' => 'ulid',       // ADR-0006
        'timestamps' => true,
        'per_page' => 15,
    ],
];

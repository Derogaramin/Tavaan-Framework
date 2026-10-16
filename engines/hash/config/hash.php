<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Hash
 * config/hash.php — پیکربندی درهم‌سازی رمز (DOC-055 §5).
 */

return [
    'driver' => env_string('TVN_HASH_DRIVER', 'argon2id'),   // argon2id|bcrypt

    'argon2id' => [
        'memory_cost' => 65536,   // 64 MB — حافظهٔ کم = ضعف در برابر GPU
        'time_cost'   => 4,
        'threads'     => 1,       // 🔒 ۱ برای جلوگیری از parallel/side-channel attack
    ],

    'bcrypt' => [
        'cost'   => 12,
        'rounds' => 12,
    ],

    // پس از verify موفق، در صورت قدیمی بودن پارامترها → rehash (DOC-055 §4)
    'rehash_on_login' => true,
];

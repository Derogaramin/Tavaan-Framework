<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Validation
 * config/validation.php — پیکربندی پیش‌فرض (DOC-051 §8).
 */

return [
    'locale'              => null,      // null = app.locale
    'bail'                => false,     // توقف در اولین خطای هر فیلد
    'stop_on_first'       => false,     // توقف کل
    'implicit_rules'      => ['required', 'required_if', 'filled', 'present'],
    'exclude_unvalidated' => true,      // validated() فقط فیلدهای قاعده‌دار
    'short_array_keys'    => false,
    'password_rules'      => ['min:12', 'letters', 'numbers', 'not_compromised'],
    'custom_rules'        => [],
    'max_depth'           => 16,        // 🔒 DoS با آرایهٔ عمیق
    'max_array_size'      => 10000,
];

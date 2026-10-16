<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Payment
 * config/payment.php — پیکربندی درگاه‌های پرداخت (DOC-073 §8).
 *
 * 🔒 همهٔ اعتبارنامه‌ها از ENV خوانده می‌شوند (هرگز هاردکد نکنید).
 */

return [
    // درگاه پیش‌فرض
    'default' => env_string('TVN_PAYMENT_GATEWAY', '') ?: 'zarinpal',

    // 💰 واحد پول پیش‌فرض (IRT = تومان — ADR-0013)
    'currency' => env_string('TVN_PAYMENT_CURRENCY', '') ?: 'IRT',

    'gateways' => [
        // ── ایرانی ────────────────────────────────────────────────────────
        'zarinpal' => [
            'merchant_id' => env_string('ZARINPAL_MERCHANT'),
            'sandbox' => env_bool('ZARINPAL_SANDBOX', false),
            'api_url' => 'https://api.zarinpal.com/pg/v4',
        ],
        'behpardakht' => [
            'terminal_id' => env_string('BPM_TERMINAL'),
            'username' => env_string('BPM_USERNAME'),
            'password' => env_string('BPM_PASSWORD'),
        ],
        'saman' => [
            'merchant_id' => env_string('SAMAN_MERCHANT'),
            'terminal_id' => env_string('SAMAN_TERMINAL'),
        ],
        'parsian' => [
            'login_account' => env_string('PARSIAN_LOGIN'),
        ],
        'payping' => [
            'token' => env_string('PAYPING_TOKEN'),
        ],
        'idpay' => [
            'api_key' => env_string('IDPAY_API_KEY'),
            'sandbox' => env_bool('IDPAY_SANDBOX', false),
        ],
        'sadad' => [
            'merchant_id' => env_string('SADAD_MERCHANT'),
            'sign_key' => env_string('SADAD_KEY'),
        ],
        'melli' => [
            'merchant_id' => env_string('MELLI_MERCHANT'),
            'sign_key' => env_string('MELLI_KEY'),
        ],

        // ── بین‌المللی ────────────────────────────────────────────────────
        'stripe' => [
            'secret_key' => env_string('STRIPE_SECRET'),
        ],

        // ── کیف پول داخلی ─────────────────────────────────────────────────
        'wallet' => [
            'enabled' => env_bool('TVN_WALLET_ENABLED', true),
            // ⚠️ فقط توسعه/تست — در production پیاده‌سازی WalletStoreInterface
            // خود را به‌عنوان `payment.wallet` در کانتینر bind کنید.
            'accounts' => [
                // 'user-1' => 5_000_000,
            ],
        ],
    ],

    'callback_url' => env_string('TVN_PAYMENT_CALLBACK_URL', '') ?: (env_string('TVN_APP_URL', '') . '/payment/callback'),
    'notify_url' => env_string('TVN_PAYMENT_NOTIFY_URL', '') ?: (env_string('TVN_APP_URL', '') . '/payment/notify'),

    // template آدرس صفحهٔ نتیجه ({id}/{status}/{order_id}/{gateway})
    'result_url' => env_string('TVN_PAYMENT_RESULT_URL', '') ?: '/payment/result?transaction={id}&status={status}',

    // 🔒 ttl کلید idempotency (ثانیه)
    'idempotency_ttl' => env_int('TVN_PAYMENT_IDEMPOTENCY_TTL', 86400),
];

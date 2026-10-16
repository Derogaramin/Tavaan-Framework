<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Report
 * config/report.php — پیکربندی گزارش‌ساز (DOC-072 §7).
 */

return [
    // قالب‌های مجاز خروجی
    'formats' => ['csv', 'xlsx', 'html', 'json', 'pdf'],

    // درایور PDF (اختیاری): null = غیرفعال، html = آمادهٔ چاپ در مرورگر
    'pdf_driver' => env_string('TVN_PDF_DRIVER', '') ?: null,

    'xlsx' => [
        // auto|ext-zip|pure — ZipWriterPure خودنوشته در همهٔ حالت‌ها کار می‌کند
        'driver' => 'auto',
    ],

    // 🔒 محافظ DoS: سقف ردیف‌های یک گزارش
    'max_rows' => env_int('TVN_REPORT_MAX_ROWS', 100_000),

    // آستانهٔ chunk در streaming (تعداد ردیف در هر تکه)
    'streaming_threshold' => env_int('TVN_REPORT_STREAM_CHUNK', 1000),

    'csv' => [
        // 🔒 `;` به‌جای `,` (سازگاری با Excel فارسی)
        'delimiter' => ';',
        // 🔒 BOM برای نمایش درست UTF-8/فارسی در Excel
        'bom' => true,
        'enclosure' => '"',
    ],

    'temp_path' => storage_path('temp'),
];

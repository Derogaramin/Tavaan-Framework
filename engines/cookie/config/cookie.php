<?php

declare(strict_types=1);

/*
 * پیکربندی کوکی — DOC-042
 */

return [
    // کوکی‌هایی که رمزنگاری نمی‌شوند (XSRF-TOKEN باید برای SPA خوانا باشد)
    'except' => ['XSRF-TOKEN'],
];

<?php

declare(strict_types=1);

/*
 * پیکربندی موتور Pipeline — DOC-034 §5
 * aliasها در فازهای بعد (auth/security/…) تکمیل می‌شوند.
 */

return [
    'aliases' => [],
    'max_depth' => 64,
    'trace' => env_bool('TVN_PIPELINE_TRACE', false),
];

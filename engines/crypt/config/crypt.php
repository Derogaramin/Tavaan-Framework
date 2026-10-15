<?php

declare(strict_types=1);

/*
 * پیکربندی موتور Crypt — DOC-054
 * کلید اصلی از app.key خوانده می‌شود (tvn key:generate).
 */

return [
    'cipher' => 'aes-256-gcm',
    'contexts' => [
        'cookie'     => 'tavaan.cookie',
        'session'    => 'tavaan.session',
        'signed_url' => 'tavaan.signed_url',
    ],
];

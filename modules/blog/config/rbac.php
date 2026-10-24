<?php

declare(strict_types=1);

/*
 * ماژول وبلاگ — نگاشت Policyهای RBAC (ادغام در rbac.policies).
 *
 * 🔒 اعلامی به‌جای Gate::policy در boot — Gate با اولین مصرف ساخته می‌شود
 * و اتصال DB کهنه capture نمی‌شود (سازگار با RefreshDatabase تست‌ها).
 */

return [
    'policies' => [
        \Modules\Blog\Domain\Entities\Post::class => \Modules\Blog\Policies\PostPolicy::class,
    ],
];

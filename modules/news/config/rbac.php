<?php

declare(strict_types=1);

/*
 * ماژول خبرگزاری — نگاشت Policyهای RBAC (ادغام در rbac.policies).
 *
 * 🔒 اعلامی به‌جای Gate::policy در boot — Gate با اولین مصرف ساخته می‌شود
 * و اتصال DB کهنه capture نمی‌شود (سازگار با RefreshDatabase تست‌ها).
 */

return [
    'policies' => [
        \Modules\News\Domain\Entities\Article::class => \Modules\News\Policies\ArticlePolicy::class,
    ],
];

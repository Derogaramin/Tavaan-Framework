<?php

declare(strict_types=1);

/*
 * ماژول LMS — نگاشت Policyهای RBAC (ادغام در rbac.policies).
 */

return [
    'policies' => [
        \Modules\Lms\Domain\Entities\Course::class => \Modules\Lms\Policies\CoursePolicy::class,
    ],
];

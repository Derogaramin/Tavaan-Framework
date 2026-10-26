<?php

declare(strict_types=1);

/*
 * ماژول BPMS — نگاشت Policyهای RBAC (ادغام در rbac.policies).
 */

return [
    'policies' => [
        \Modules\Bpms\Domain\Entities\Task::class => \Modules\Bpms\Policies\TaskPolicy::class,
    ],
];

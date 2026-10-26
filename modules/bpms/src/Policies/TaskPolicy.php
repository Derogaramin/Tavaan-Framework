<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * TaskPolicy — دسترسی کارها (DOC-098 §7).
 */

namespace Modules\Bpms\Policies;

use Modules\Bpms\Domain\Entities\Task;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * TaskPolicy — لایهٔ مجوز (permission)؛ لایهٔ مالکیت (assignee/role/
 * initiator) در ProcessEngine.canWork بررسی می‌شود.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class TaskPolicy
{
    public function view(Authenticatable $user, Task $task): bool
    {
        return Gate::allows('bpms.task.view-all') || Gate::allows('bpms.task.view');
    }

    public function claim(Authenticatable $user, Task $task): bool
    {
        return Gate::allows('bpms.task.claim');
    }

    public function complete(Authenticatable $user, Task $task): bool
    {
        return Gate::allows('bpms.task.complete');
    }

    public function delegate(Authenticatable $user, Task $task): bool
    {
        return Gate::allows('bpms.task.delegate') || Gate::allows('bpms.task.assign');
    }
}

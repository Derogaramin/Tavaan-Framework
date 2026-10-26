<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * TaskController — inbox و انجام کارها (DOC-098 §7: task-assignee).
 */

namespace Modules\Bpms\Http\Controllers\Web;

use Modules\Bpms\Application\UseCases\ProcessEngine;
use Modules\Bpms\Domain\Entities\Task;
use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\Repositories\DefinitionRepositoryInterface;
use Modules\Bpms\Domain\Repositories\InstanceRepositoryInterface;
use Modules\Bpms\Domain\Repositories\TaskRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * TaskController — «کارهای من»: فهرست (مستقیم + نقش قابل تصاحب)،
 * جزئیات با **رندر فرم پویا از form_schema** (§4)، claim/complete/delegate.
 *
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class TaskController
{
    public function __construct(
        private readonly ProcessEngine $engine,
        private readonly TaskRepositoryInterface $tasks,
        private readonly InstanceRepositoryInterface $instances,
        private readonly DefinitionRepositoryInterface $definitions,
    ) {
    }

    public function index(): mixed
    {
        $this->authorize('bpms.task.view');

        $userId = $this->actorId();
        $inbox = $this->tasks->inboxFor($userId, $this->engine->rolesOf($userId));

        $claimed = [];
        $available = [];
        foreach ($inbox as $task) {
            if ($task->status()->value === 'claimed' && $task->claimedBy() === $userId) {
                $claimed[] = $task;
            } else {
                $available[] = $task;
            }
        }

        return View::render('bpms::web.tasks-index', [
            'claimed' => $claimed,
            'available' => $available,
        ]);
    }

    public function show(string $id): mixed
    {
        $task = $this->findOr404($id);
        $this->assertCanSee($task);

        $instance = $this->instances->findById($task->instanceId());
        $definition = $instance !== null ? $this->definitions->findById($instance->definitionId()) : null;

        $form = null;
        if ($definition !== null && $task->formId() !== null) {
            $form = $definition->formSchema($task->formId());
        }

        return View::render('bpms::web.task-show', [
            'task' => $task,
            'instance' => $instance,
            'definition' => $definition,
            'form' => $form,
            'canClaim' => Gate::allows('bpms.task.claim') && $task->status()->value === 'pending',
            'canComplete' => Gate::allows('bpms.task.complete') && $task->status()->isOpen(),
            'canDelegate' => Gate::allows('bpms.task.delegate') && $task->status()->isOpen(),
        ]);
    }

    public function claim(string $id): mixed
    {
        $this->findOr404($id);
        $this->authorize('bpms.task.claim');

        try {
            $this->engine->claim($id, $this->actorId(), $this->engine->rolesOf($this->actorId()));
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'task_claimed');

        return back();
    }

    public function complete(RequestInterface $request, string $id): mixed
    {
        $this->findOr404($id);
        $this->authorize('bpms.task.complete');

        // دادهٔ فرم: فقط فیلدهای ارسال‌شده (schema در engine نرمال/validate می‌شود)
        $formData = $request->all();
        unset($formData['_token'], $formData['comment']);

        $comment = (string) $request->input('comment', '');
        try {
            $this->engine->completeTask(
                $id,
                $formData,
                $this->actorId(),
                $this->engine->rolesOf($this->actorId()),
                $comment === '' ? null : $comment,
            );
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', 'دادهٔ فرم معتبر نیست.');

            return back();
        }

        Session::flash('status', 'task_completed');

        return redirect('/bpms/tasks');
    }

    public function delegate(RequestInterface $request, string $id): mixed
    {
        $this->findOr404($id);
        $this->authorize('bpms.task.delegate');

        $targetType = (string) $request->input('assignee_type', 'user');
        $targetId = (string) $request->input('assignee_id', '');

        try {
            $this->engine->delegate($id, $this->actorId(), $this->engine->rolesOf($this->actorId()), $targetType, $targetId);
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'task_delegated');

        return back();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id): Task
    {
        $task = $this->tasks->findById($id);
        if ($task === null) {
            throw new RouteNotFoundException(messageId: 'bpms::messages.task_not_found', context: ['id' => $id]);
        }

        return $task;
    }

    /** دسترسی مشاهده: همه‌بین‌ها یا assignee/claimant (inbox). */
    private function assertCanSee(Task $task): void
    {
        if (Gate::allows('bpms.task.view-all')) {
            return;
        }
        $this->authorize('bpms.task.view');

        $userId = $this->actorId();
        $assigneeId = $task->assigneeId();
        $isMine = $assigneeId !== null && (string) $userId === $assigneeId;
        $isClaimant = $task->claimedBy() === $userId;
        $isRoleTask = \in_array($task->assigneeType()->value, ['role', 'group', 'anyone'], true)
            && ($task->assigneeId() === null || \in_array($task->assigneeId(), $this->engine->rolesOf($userId), true));

        if (!$isMine && !$isClaimant && !$isRoleTask) {
            throw new ForbiddenException(messageId: 'bpms::messages.forbidden', context: ['permission' => 'bpms.task.view']);
        }
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'bpms::messages.forbidden', context: ['permission' => $permission]);
        }
    }

    private function actorId(): int
    {
        return (int) Auth::id();
    }
}

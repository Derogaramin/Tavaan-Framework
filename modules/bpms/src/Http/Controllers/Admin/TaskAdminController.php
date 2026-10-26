<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * TaskAdminController — نمای مدیر کارها (انتصاب/تکمیل اجباری — §7).
 */

namespace Modules\Bpms\Http\Controllers\Admin;

use Modules\Bpms\Application\UseCases\BpmsAuditRecorder;
use Modules\Bpms\Application\UseCases\ProcessEngine;
use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\Repositories\TaskRepositoryInterface;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class TaskAdminController
{
    public function __construct(
        private readonly ProcessEngine $engine,
        private readonly TaskRepositoryInterface $tasks,
        private readonly BpmsAuditRecorder $audit,
        private readonly UserReaderInterface $users,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('bpms.task.view-all');

        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        if (($status = (string) $request->input('status', '')) !== '') {
            $filters['status'] = $status;
        }
        if ($request->input('overdue') === '1') {
            $filters['overdue_now'] = \Tavaan\Support\Time\Clock::now()->format('Y-m-d H:i:s.v');
        }

        $result = $this->tasks->paginate($filters, $page, 20);

        return View::render('bpms::admin.tasks-index', [
            'tasks' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'statusFilter' => (string) $request->input('status', ''),
            'users' => $this->assignableUsers(),
        ]);
    }

    /** انتصاب اجباری (delegate با مجوز bpms.task.assign). */
    public function assign(RequestInterface $request, string $id): mixed
    {
        $this->authorize('bpms.task.assign');
        $this->taskOr404($id);

        $targetType = (string) $request->input('assignee_type', 'user');
        $targetId = (string) $request->input('assignee_id', '');
        if ($targetId === '') {
            Session::flash('error', 'مقصد انتصاب نامعتبر است.');

            return back();
        }

        try {
            $this->engine->delegate($id, $this->actorId(), $this->engine->rolesOf($this->actorId()), $targetType, $targetId, canOverride: true);
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        $this->audit->record('task.assigned', 'bpms_task', $id, [
            'assignee_type' => $targetType, 'assignee_id' => $targetId,
        ], $this->actorId());
        Session::flash('status', 'task_assigned');

        return back();
    }

    /** تکمیل اجباری از طرف مدیر (با دادهٔ فرم دلخواه). */
    public function complete(RequestInterface $request, string $id): mixed
    {
        $this->authorize('bpms.task.assign');
        $this->taskOr404($id);

        $formData = [];
        $raw = $request->input('form_data');
        if (\is_string($raw) && \trim($raw) !== '') {
            try {
                $decoded = \json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                $formData = \is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                Session::flash('error', 'form_data باید JSON معتبر باشد.');

                return back();
            }
        } elseif (\is_array($raw)) {
            $formData = $raw;
        }

        try {
            $this->engine->completeTask(
                $id,
                $formData,
                $this->actorId(),
                $this->engine->rolesOf($this->actorId()),
                (string) $request->input('comment', '') !== '' ? (string) $request->input('comment') : null,
                canOverride: true,
            );
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        $this->audit->record('task.force_completed', 'bpms_task', $id, ['by' => $this->actorId()], $this->actorId());
        Session::flash('status', 'task_completed');

        return back();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function taskOr404(string $id): void
    {
        if ($this->tasks->findById($id) === null) {
            throw new RouteNotFoundException(messageId: 'bpms::messages.task_not_found', context: ['id' => $id]);
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

    /** @return list<array{id: int, name: string}> */
    private function assignableUsers(): array
    {
        try {
            $rows = DB::table('users')->orderBy('name')->limit(200)->get();

            return \array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name']], $rows);
        } catch (\Throwable) {
            return [];
        }
    }
}

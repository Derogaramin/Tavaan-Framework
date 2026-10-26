<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * InstanceController — مدیریت نمونه‌های فرایند (DOC-098 §7: process-admin).
 */

namespace Modules\Bpms\Http\Controllers\Admin;

use Modules\Bpms\Application\UseCases\BpmsAuditRecorder;
use Modules\Bpms\Application\UseCases\ProcessEngine;
use Modules\Bpms\Domain\Entities\ProcessInstance;
use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\Repositories\DefinitionRepositoryInterface;
use Modules\Bpms\Domain\Repositories\HistoryRepositoryInterface;
use Modules\Bpms\Domain\Repositories\InstanceRepositoryInterface;
use Modules\Bpms\Domain\Repositories\TaskRepositoryInterface;
use Modules\Bpms\Domain\Services\DiagramGenerator;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class InstanceController
{
    public function __construct(
        private readonly ProcessEngine $engine,
        private readonly InstanceRepositoryInterface $instances,
        private readonly DefinitionRepositoryInterface $definitions,
        private readonly TaskRepositoryInterface $tasks,
        private readonly HistoryRepositoryInterface $history,
        private readonly DiagramGenerator $diagrams,
        private readonly BpmsAuditRecorder $audit,
        private readonly UserReaderInterface $users,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('bpms.instance.view');

        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        foreach (['status', 'definition_key'] as $key) {
            if (($value = (string) $request->input($key, '')) !== '') {
                $filters[$key] = $value;
            }
        }
        if (($q = (string) $request->input('q', '')) !== '') {
            $filters['search'] = $q;
        }
        if (!Gate::allows('bpms.instance.view-all')) {
            $filters['initiator_id'] = (int) Auth::id();
        }

        $result = $this->instances->paginate($filters, $page, 20);

        return View::render('bpms::admin.instances-index', [
            'instances' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'query' => (string) $request->input('q', ''),
            'statusFilter' => (string) $request->input('status', ''),
            'initiators' => $this->displayNames($result['rows']),
        ]);
    }

    /** صفحهٔ آغاز فرایند (کاتالوگ تعاریف فعال). */
    public function create(): mixed
    {
        $this->authorize('bpms.instance.start');

        $startable = [];
        foreach ($this->definitions->paginate(['active' => true, 'status' => 'published'], 1, 100)['rows'] as $definition) {
            if ($definition->isStartable()) {
                $startable[] = $definition;
            }
        }

        return View::render('bpms::admin.instance-start', [
            'definitions' => $startable,
        ]);
    }

    /** آغاز نمونه (§5: start). */
    public function store(RequestInterface $request): mixed
    {
        $this->authorize('bpms.instance.start');

        $key = (string) $request->input('definition_key', '');
        $businessKey = (string) $request->input('business_key', '');
        $variables = $this->decodeVariables($request->input('variables'));

        if ($variables === null) {
            Session::flash('error', 'متغیرها باید JSON معتبر باشند.');

            return back();
        }

        try {
            $instance = $this->engine->start(
                $key,
                $variables,
                $this->actorId(),
                $businessKey === '' ? null : $businessKey,
            );
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'process_started');

        return redirect('/admin/bpms/instances/' . $instance->id());
    }

    public function show(string $id): mixed
    {
        $instance = $this->findOr404($id);
        $this->assertCanView($instance);

        $definition = $this->definitions->findById($instance->definitionId());
        $tasks = $this->tasks->forInstance($instance->id());
        $history = $this->history->forInstance($instance->id());

        return View::render('bpms::admin.instance-show', [
            'instance' => $instance,
            'definition' => $definition,
            'tasks' => $tasks,
            'history' => $history,
            'variables' => $this->maskVariables($instance->variables()),
            'svg' => $definition !== null ? $this->diagrams->toSvg($definition->model(), $instance->tokens()) : '',
            'initiatorName' => $instance->initiatorId() !== null
                ? $this->users->displayNameOf($instance->initiatorId(), '—')
                : '—',
            'canManage' => Gate::allows('bpms.instance.manage'),
            'canTerminate' => Gate::allows('bpms.instance.terminate'),
        ]);
    }

    public function terminate(RequestInterface $request, string $id): mixed
    {
        $this->findOr404($id);
        $this->authorize('bpms.instance.terminate');

        $reason = (string) $request->input('reason', '');
        try {
            $this->engine->terminate($id, $this->actorId(), $reason === '' ? null : $reason);
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        $this->audit->record('instance.terminated', 'bpms_instance', $id, ['reason' => $reason], $this->actorId());
        Session::flash('status', 'instance_terminated');

        return back();
    }

    public function suspend(string $id): mixed
    {
        $this->findOr404($id);
        $this->authorize('bpms.instance.manage');

        try {
            $this->engine->suspend($id, $this->actorId());
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        $this->audit->record('instance.suspended', 'bpms_instance', $id, [], $this->actorId());
        Session::flash('status', 'instance_suspended');

        return back();
    }

    public function resume(string $id): mixed
    {
        $this->findOr404($id);
        $this->authorize('bpms.instance.manage');

        try {
            $this->engine->resume($id, $this->actorId());
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        $this->audit->record('instance.resumed', 'bpms_instance', $id, [], $this->actorId());
        Session::flash('status', 'instance_resumed');

        return back();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id): ProcessInstance
    {
        $instance = $this->instances->findById($id);
        if ($instance === null) {
            throw new RouteNotFoundException(messageId: 'bpms::messages.instance_not_found', context: ['id' => $id]);
        }

        return $instance;
    }

    private function assertCanView(ProcessInstance $instance): void
    {
        if (Gate::allows('bpms.instance.view-all') || Gate::allows('bpms.monitor.view')) {
            return;
        }
        if (Gate::allows('bpms.instance.view') && $instance->initiatorId() === $this->actorId()) {
            return;
        }

        throw new ForbiddenException(messageId: 'bpms::messages.forbidden', context: ['permission' => 'bpms.instance.view']);
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'bpms::messages.forbidden', context: ['permission' => $permission]);
        }
    }

    private function actorId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }

    /** @return array<string, mixed>|null */
    private function decodeVariables(mixed $raw): ?array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        if (\is_array($raw)) {
            return $raw;
        }
        if (\is_string($raw)) {
            try {
                $decoded = \json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return null;
            }

            return \is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /** 🔒 mask کلیدهای حساس (config bpms.masked_variables — DOC-071 §8). */
    private function maskVariables(array $variables): array
    {
        $patterns = (array) Config::get('bpms.masked_variables', []);
        foreach ($variables as $key => $value) {
            foreach ($patterns as $pattern) {
                if (\is_string($pattern) && $pattern !== '' && \str_contains(\strtolower((string) $key), \strtolower($pattern))) {
                    $variables[$key] = '***';
                    break;
                }
            }
        }

        return $variables;
    }

    /**
     * @param list<ProcessInstance> $instances
     *
     * @return array<string, string> initiatorId → نام نمایشی
     */
    private function displayNames(array $instances): array
    {
        $ids = [];
        foreach ($instances as $instance) {
            if ($instance->initiatorId() !== null) {
                $ids[] = $instance->initiatorId();
            }
        }
        if ($ids === []) {
            return [];
        }

        $names = [];
        foreach ($this->users->findByIds(\array_values(\array_unique($ids))) as $id => $snapshot) {
            $name = \method_exists($snapshot, 'displayName') ? (string) $snapshot->displayName() : (string) ($snapshot->name ?? '');
            $names[(string) $id] = $name !== '' ? $name : '—';
        }

        return $names;
    }
}

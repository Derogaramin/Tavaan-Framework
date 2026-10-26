<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ProcessController — کاتالوگ فرایندها، آغاز و نمونه‌های من (DOC-098 §5/§7).
 */

namespace Modules\Bpms\Http\Controllers\Web;

use Modules\Bpms\Application\UseCases\ProcessEngine;
use Modules\Bpms\Domain\Entities\ProcessInstance;
use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\Repositories\DefinitionRepositoryInterface;
use Modules\Bpms\Domain\Repositories\HistoryRepositoryInterface;
use Modules\Bpms\Domain\Repositories\InstanceRepositoryInterface;
use Modules\Bpms\Domain\Repositories\TaskRepositoryInterface;
use Modules\Bpms\Domain\Services\DiagramGenerator;
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
final class ProcessController
{
    public function __construct(
        private readonly ProcessEngine $engine,
        private readonly DefinitionRepositoryInterface $definitions,
        private readonly InstanceRepositoryInterface $instances,
        private readonly TaskRepositoryInterface $tasks,
        private readonly HistoryRepositoryInterface $history,
        private readonly DiagramGenerator $diagrams,
    ) {
    }

    /** کاتالوگ فرایندهای فعال (قابل آغاز). */
    public function index(): mixed
    {
        $this->authorize('bpms.instance.start');

        $startable = [];
        foreach ($this->definitions->paginate(['active' => true, 'status' => 'published'], 1, 100)['rows'] as $definition) {
            if ($definition->isStartable()) {
                $startable[] = $definition;
            }
        }

        return View::render('bpms::web.processes-index', [
            'definitions' => $startable,
        ]);
    }

    /** صفحهٔ آغاز: business_key + متغیرهای اولیه (JSON — اختیاری). */
    public function create(string $key): mixed
    {
        $this->authorize('bpms.instance.start');

        $definition = $this->definitions->findActiveByKey($key);
        if ($definition === null) {
            throw new RouteNotFoundException(messageId: 'bpms::messages.definition_not_found', context: ['key' => $key]);
        }

        return View::render('bpms::web.process-start', [
            'definition' => $definition,
            'svg' => $this->diagrams->toSvg($definition->model()),
        ]);
    }

    public function store(RequestInterface $request, string $key): mixed
    {
        $this->authorize('bpms.instance.start');

        $businessKey = (string) $request->input('business_key', '');
        $variables = [];
        $rawVariables = $request->input('variables');
        if (\is_string($rawVariables) && \trim($rawVariables) !== '') {
            try {
                $decoded = \json_decode($rawVariables, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                Session::flash('error', 'متغیرها باید JSON معتبر باشند.');

                return back();
            }
            if (!\is_array($decoded)) {
                Session::flash('error', 'متغیرها باید JSON آبجکت باشند.');

                return back();
            }
            $variables = $decoded;
        } elseif (\is_array($rawVariables)) {
            $variables = $rawVariables;
        }

        try {
            $instance = $this->engine->start($key, $variables, $this->actorId(), $businessKey === '' ? null : $businessKey);
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'process_started');

        return redirect('/bpms/instances/' . $instance->id());
    }

    /** نمونه‌های من (یا همه با bpms.instance.view-all). */
    public function instances(RequestInterface $request): mixed
    {
        $this->authorize('bpms.instance.view');

        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        if (!Gate::allows('bpms.instance.view-all')) {
            $filters['initiator_id'] = $this->actorId();
        }
        if (($status = (string) $request->input('status', '')) !== '') {
            $filters['status'] = $status;
        }

        $result = $this->instances->paginate($filters, $page, 20);

        return View::render('bpms::web.instances-index', [
            'instances' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'statusFilter' => (string) $request->input('status', ''),
        ]);
    }

    public function show(string $id): mixed
    {
        $instance = $this->findOr404($id);
        $this->assertCanView($instance);

        $definition = $this->definitions->findById($instance->definitionId());

        return View::render('bpms::web.instance-show', [
            'instance' => $instance,
            'definition' => $definition,
            'tasks' => $this->tasks->forInstance($instance->id()),
            'history' => $this->history->forInstance($instance->id()),
            'variables' => $this->maskVariables($instance->variables()),
            'svg' => $definition !== null ? $this->diagrams->toSvg($definition->model(), $instance->tokens()) : '',
        ]);
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

    private function actorId(): int
    {
        return (int) Auth::id();
    }

    /** 🔒 mask کلیدهای حساس (config bpms.masked_variables). */
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
}

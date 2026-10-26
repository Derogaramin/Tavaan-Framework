<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * DefinitionController — فرایندساز: تعریف/نسخه/انتشار (DOC-098 §1.1).
 */

namespace Modules\Bpms\Http\Controllers\Admin;

use Modules\Bpms\Application\UseCases\DesignerService;
use Modules\Bpms\Domain\Entities\ProcessDefinition;
use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\Repositories\DefinitionRepositoryInterface;
use Modules\Bpms\Domain\Services\DiagramGenerator;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class DefinitionController
{
    public function __construct(
        private readonly DesignerService $designer,
        private readonly DefinitionRepositoryInterface $definitions,
        private readonly DiagramGenerator $diagrams,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('bpms.definition.view');

        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        if (($q = (string) $request->input('q', '')) !== '') {
            $filters['search'] = $q;
        }
        if (($status = (string) $request->input('status', '')) !== '') {
            $filters['status'] = $status;
        }

        $result = $this->definitions->paginate($filters, $page, 20);

        return View::render('bpms::admin.definitions-index', [
            'definitions' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'query' => (string) $request->input('q', ''),
            'statusFilter' => (string) $request->input('status', ''),
        ]);
    }

    public function create(): mixed
    {
        $this->authorize('bpms.definition.manage');

        return View::render('bpms::admin.definition-form', [
            'definition' => null,
            'definitionJson' => $this->templateJson(),
            'formsJson' => $this->templateFormsJson(),
        ]);
    }

    public function store(RequestInterface $request): mixed
    {
        $this->authorize('bpms.definition.manage');

        try {
            $definition = $this->designer->createDraft([
                'key' => (string) $request->input('key', ''),
                'name' => (string) $request->input('name', ''),
                'description' => $request->input('description'),
                'definition' => $request->input('definition'),
                'form_schema' => $request->input('form_schema'),
            ], $this->actorId());
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'definition_created');

        return redirect('/admin/bpms/definitions/' . $definition->id());
    }

    public function show(string $id): mixed
    {
        $definition = $this->findOr404($id);
        $this->authorize('bpms.definition.view');

        return View::render('bpms::admin.definition-show', [
            'definition' => $definition,
            'versions' => $this->definitions->versionsOf($definition->key()),
            'svg' => $this->diagrams->toSvg($definition->model()),
            'mermaid' => $this->diagrams->toMermaid($definition->model()),
            'canManage' => Gate::allows('bpms.definition.manage'),
            'canPublish' => Gate::allows('bpms.definition.publish'),
        ]);
    }

    public function edit(string $id): mixed
    {
        $definition = $this->findOr404($id);
        $this->authorize('bpms.definition.manage');

        if (!$definition->status()->isEditable()) {
            Session::flash('error', 'فقط پیش‌نویس قابل ویرایش است — نسخهٔ جدید از روی آن بسازید.');

            return redirect('/admin/bpms/definitions/' . $id);
        }

        $forms = [];
        foreach ($definition->formSchemas() as $formId => $schema) {
            $forms[$formId] = $schema->toArray();
        }

        return View::render('bpms::admin.definition-form', [
            'definition' => $definition,
            'definitionJson' => (string) \json_encode($definition->model()->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'formsJson' => (string) \json_encode($forms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** نمودار SVG (لایهٔ طراحی بصری — inline). */
    public function diagram(string $id): mixed
    {
        $definition = $this->findOr404($id);
        $this->authorize('bpms.definition.view');

        /** @var object $responses */
        $responses = \app()->make('response.factory');

        return $responses->make(
            $this->diagrams->toSvg($definition->model()),
            200,
            ['Content-Type' => 'image/svg+xml; charset=UTF-8'],
        );
    }

    public function update(RequestInterface $request, string $id): mixed
    {
        $definition = $this->findOr404($id);
        $this->authorize('bpms.definition.manage');

        $input = [];
        foreach (['name', 'description', 'definition', 'form_schema'] as $key) {
            if ($request->input($key) !== null) {
                $input[$key] = $request->input($key);
            }
        }

        try {
            $this->designer->updateDraft($definition->id(), $input, $this->actorId());
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'definition_updated');

        return redirect('/admin/bpms/definitions/' . $id);
    }

    public function publish(string $id): mixed
    {
        $this->findOr404($id);
        $this->authorize('bpms.definition.publish');

        try {
            $this->designer->publish($id, $this->actorId());
        } catch (ValidationException|BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'definition_published');

        return back();
    }

    public function activate(string $id): mixed
    {
        $this->findOr404($id);
        $this->authorize('bpms.definition.publish');

        try {
            $this->designer->activate($id, $this->actorId());
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'definition_activated');

        return back();
    }

    public function retire(string $id): mixed
    {
        $this->findOr404($id);
        $this->authorize('bpms.definition.publish');

        try {
            $this->designer->retire($id, $this->actorId());
        } catch (BpmsException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'definition_retired');

        return back();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id): ProcessDefinition
    {
        $definition = $this->definitions->findById($id);
        if ($definition === null) {
            throw new RouteNotFoundException(messageId: 'bpms::messages.definition_not_found', context: ['id' => $id]);
        }

        return $definition;
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

    /** الگوی اولیهٔ JSON تعریف (DOC-098 §3). */
    private function templateJson(): string
    {
        $template = [
            'startEvent' => 'start',
            'elements' => [
                ['id' => 'start', 'type' => 'startEvent', 'next' => 'first_task'],
                ['id' => 'first_task', 'type' => 'userTask', 'name' => 'کار اول', 'assignee' => 'initiator', 'form' => 'start_form', 'next' => 'end'],
                ['id' => 'end', 'type' => 'endEvent'],
            ],
            'timers' => [],
        ];

        return (string) \json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** الگوی اولیهٔ form_schema (DOC-098 §4). */
    private function templateFormsJson(): string
    {
        $template = [
            'start_form' => [
                'id' => 'start_form',
                'title' => 'فرم آغاز',
                'fields' => [
                    ['name' => 'title', 'type' => 'text', 'label' => 'عنوان', 'required' => true, 'max' => 190],
                    ['name' => 'description', 'type' => 'textarea', 'label' => 'شرح'],
                ],
            ],
        ];

        return (string) \json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

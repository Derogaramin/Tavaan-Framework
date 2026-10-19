<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Http;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Engine\Admin\AdminManager;
use Tavaan\Engine\Admin\Form\Field;
use Tavaan\Engine\Admin\Resource;
use Tavaan\Engine\Admin\Support\ValueTransformer;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Http\Redirector;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Rbac\Gate;
use Tavaan\Engine\Validation\Validator;

/**
 * تَوان | Tavaan — Admin
 * AdminController — کنترلر عمومی CRUD برای همهٔ resourceها (DOC-078 §3).
 *
 * 🔒 هر عملیات با Gate/Permission محافظت می‌شود (AC-ADM-02)؛ ورودی فقط از
 * whitelist فیلدهای فرم (§7 Mass Assignment)؛ validation خودکار از Form.
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class AdminController
{
    private readonly AdminManager $admin;

    private readonly Gate $gate;

    private readonly ValueTransformer $transformer;

    public function __construct(private readonly ContainerInterface $app)
    {
        $this->admin = $this->app->make(AdminManager::class);
        $this->gate = $this->app->make(Gate::class);
        $this->transformer = new ValueTransformer($this->app);
    }

    // ─── List ─────────────────────────────────────────────────────────────

    public function index(Request $request, string $resourceKey): Response
    {
        $resource = $this->resourceOrFail($resourceKey);
        $this->authorize($resource->permissionFor('view'));

        $modelClass = $resource->modelClass();
        $query = $modelClass::query();
        $resource->queryCustomizer()?->__invoke($query);

        $search = \trim((string) $request->query('q', ''));
        if ($search !== '' && $resource->searchableFields() !== []) {
            $searchable = $resource->searchableFields();
            $query->where(static function (object $sub) use ($searchable, $search): void {
                foreach ($searchable as $column) {
                    $sub->orWhere($column, 'like', '%' . $search . '%');
                }
            });
        }

        foreach (\array_keys($resource->filters()) as $column) {
            $value = $request->query((string) $column);
            if ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }

        $sort = (string) $request->query('sort', '');
        if ($sort !== '' && \in_array($sort, $resource->sortableColumns(), true)) {
            $direction = \strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sort, $direction);
        } else {
            // 🔒 pagination قطعی: بدون ORDER BY نتیجهٔ LIMIT/OFFSET در SQLite نامعین است
            $query->orderBy((new $modelClass())->getKeyName(), 'asc');
        }

        $perPage = $resource->perPageCount((int) ($this->config('per_page', 25)));
        $page = \max(1, (int) $request->query('page', 1));
        $paginator = $query->paginate($perPage, $page);

        $rows = [];
        foreach ($paginator->items() as $item) {
            $rows[] = [
                'id' => (string) ($item instanceof Model ? (string) ($item->getKey() ?? '') : ''),
                'model' => $item,
                'cells' => $this->displayRow($resource, $item),
            ];
        }

        return $this->html('admin::list', [
            'resource' => $resource,
            'rows' => $rows,
            'paginator' => $paginator,
            'search' => $search,
            'sort' => $sort,
            'dir' => \strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc',
            'filters' => $this->currentFilters($request, $resource),
            'actions' => $this->allowedActions($resource, bulk: false),
            'bulkActions' => $this->allowedActions($resource, bulk: true),
            'can' => $this->permissions($resource),
            'menu' => $this->menuItems(),
            'adminPrefix' => $this->admin->prefix(),
            'token' => $this->token($request),
            'appTitle' => $this->appTitle(),
        ]);
    }

    // ─── Create / Store ───────────────────────────────────────────────────

    public function create(Request $request, string $resourceKey): Response
    {
        $resource = $this->resourceOrFail($resourceKey);
        $this->authorize($resource->permissionFor('create'));

        return $this->formResponse($request, $resource, model: null);
    }

    public function store(Request $request, string $resourceKey): Response
    {
        $resource = $this->resourceOrFail($resourceKey);
        $this->authorize($resource->permissionFor('create'));

        $data = $this->validatedData($request, $resource);
        if ($data === null) {
            return $this->backWithErrors($request);
        }

        $modelClass = $resource->modelClass();
        Model::unguard(true);
        try {
            $model = new $modelClass($data);
            $model->save();
        } finally {
            Model::unguard(false);
        }

        return $this->redirectToIndex($resource);
    }

    // ─── Show ─────────────────────────────────────────────────────────────

    public function show(Request $request, string $resourceKey, string $id): Response
    {
        $resource = $this->resourceOrFail($resourceKey);
        $this->authorize($resource->permissionFor('view'));

        $model = $this->findModelOrFail($resource, $id);
        $show = $resource->showDefinition();
        $fields = $show->fieldList() ?? ($resource->columnList() !== [] ? $resource->columnList() : ['id']);

        $panels = [];
        foreach ($show->relationPanels() as $relationName => $columns) {
            $related = $model->{$relationName} ?? null;
            $items = $related instanceof Model ? [$related] : (\is_array($related) ? $related : ($related instanceof \Traversable ? \iterator_to_array($related) : []));
            $rows = [];
            foreach ($items as $item) {
                $row = [];
                foreach ($columns as $column) {
                    $row[$column] = \is_object($item) ? ($item->{$column} ?? null) : null;
                }
                $rows[] = $row;
            }
            $panels[] = ['name' => $relationName, 'columns' => $columns, 'rows' => $rows];
        }

        return $this->html('admin::show', [
            'resource' => $resource,
            'model' => $model,
            'fields' => $fields,
            'form' => $resource->buildForm(),
            'panels' => $panels,
            'actions' => $this->allowedActions($resource, bulk: false),
            'can' => $this->permissions($resource),
            'menu' => $this->menuItems(),
            'adminPrefix' => $this->admin->prefix(),
            'token' => $this->token($request),
            'transformer' => $this->transformer,
            'appTitle' => $this->appTitle(),
        ]);
    }

    // ─── Edit / Update / Delete ───────────────────────────────────────────

    public function edit(Request $request, string $resourceKey, string $id): Response
    {
        $resource = $this->resourceOrFail($resourceKey);
        $this->authorize($resource->permissionFor('update'));

        $model = $this->findModelOrFail($resource, $id);

        return $this->formResponse($request, $resource, model: $model);
    }

    public function update(Request $request, string $resourceKey, string $id): Response
    {
        $resource = $this->resourceOrFail($resourceKey);
        $this->authorize($resource->permissionFor('update'));

        $model = $this->findModelOrFail($resource, $id);
        $data = $this->validatedData($request, $resource);
        if ($data === null) {
            return $this->backWithErrors($request);
        }

        Model::unguard(true);
        try {
            $model->fill($data);
            $model->save();
        } finally {
            Model::unguard(false);
        }

        return $this->redirectToIndex($resource);
    }

    public function destroy(Request $request, string $resourceKey, string $id): Response
    {
        unset($request);
        $resource = $this->resourceOrFail($resourceKey);
        $this->authorize($resource->permissionFor('delete'));

        $model = $this->findModelOrFail($resource, $id);
        $model->delete();

        return $this->redirectToIndex($resource);
    }

    // ─── Actions ──────────────────────────────────────────────────────────

    public function runAction(Request $request, string $resourceKey, string $id, string $action): Response
    {
        $resource = $this->resourceOrFail($resourceKey);
        $actionObject = $resource->action($action, bulk: false);
        if ($actionObject === null || !$actionObject->isAllowed($this->gate)) {
            throw new ForbiddenException();
        }

        $model = $this->findModelOrFail($resource, $id);
        $result = $actionObject->run($model, $request->all());

        return $result instanceof Response ? $result : $this->back($request);
    }

    public function runBulkAction(Request $request, string $resourceKey, string $action): Response
    {
        $resource = $this->resourceOrFail($resourceKey);
        $actionObject = $resource->action($action, bulk: true);
        if ($actionObject === null || !$actionObject->isAllowed($this->gate)) {
            throw new ForbiddenException();
        }

        $ids = (array) $request->input('ids', []);
        $affected = 0;
        foreach ($ids as $id) {
            $model = $resource->modelClass()::find((string) $id);
            if ($model === null) {
                continue;
            }
            $actionObject->run($model, $request->all());
            $affected++;
        }

        $session = $request->hasSession() ? $request->session() : null;
        $session?->flash('admin_bulk_result', ['action' => $action, 'affected' => $affected]);

        return $this->back($request);
    }

    // ─── internals ────────────────────────────────────────────────────────

    private function resourceOrFail(string $key): Resource
    {
        $resource = $this->admin->find($key);
        if ($resource === null) {
            throw RouteNotFoundException::forPath('GET', '/admin/' . $key);
        }

        return $resource;
    }

    private function authorize(string $permission): void
    {
        if (!$this->gate->allows($permission)) {
            throw new ForbiddenException(context: ['ability' => $permission]);
        }
    }

    private function findModelOrFail(Resource $resource, string $id): Model
    {
        $modelClass = $resource->modelClass();
        $model = $modelClass::find($id);
        if (!$model instanceof Model) {
            throw RouteNotFoundException::forPath('GET', '/admin/' . $resource->key() . '/' . $id);
        }

        return $model;
    }

    /**
     * whitelist → validate (روی ورودی خام) → transform (jalali/hash/sanitize/…)
     * — خروجی null یعنی validation شکست خورده (errors در session flash شد).
     *
     * ترتیب مهم است: قواعد (مثل regex جلالی) روی ورودی خام کاربر اعمال می‌شوند،
     * نه مقدار تبدیل‌شده. فیلد اختیاریِ ارسال‌نشده → skip (default دیتابیس).
     *
     * @return array<string, mixed>|null
     */
    private function validatedData(Request $request, Resource $resource): ?array
    {
        $form = $resource->buildForm();

        // ۱) whitelist — فقط فیلدهای تعریف‌شدهٔ فرم (§7 Mass Assignment)
        $raw = [];
        foreach ($form->fields() as $field) {
            if ($field->type !== 'static') {
                $raw[$field->name] = $request->input($field->name);
            }
        }

        // ۲) validation خودکار از تعریف Form (§3)
        $validator = new Validator($raw, $form->rules());
        if ($validator->fails()) {
            $session = $request->hasSession() ? $request->session() : null;
            $session?->flash('errors', $validator->errors()->toArray());
            $session?->flashInput($raw);

            return null;
        }

        // ۳) تبدیل مقادیر معتبرشده → ذخیره‌گاه
        $validated = $validator->validated();
        $data = [];
        foreach ($form->fields() as $field) {
            if ($field->type === 'static' || !\array_key_exists($field->name, $validated)) {
                continue;
            }
            $value = $validated[$field->name];
            if ($value === null && !$field->isRequired()
                && !\in_array($field->type, ['switch', 'checkbox'], true)) {
                continue;   // ارسال نشده → default ستون اعمال شود (NOT NULL نقض نشود)
            }
            $transformed = $this->transformer->toStorage($field, $value, $raw);
            if ($transformed['skip']) {
                continue;   // password خالی → دست‌نخورده
            }
            $data[$field->name] = $transformed['value'];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function displayRow(Resource $resource, object $model): array
    {
        $form = $resource->buildForm();
        $cells = [];
        foreach ($resource->columnList() as $column) {
            $value = $model->{$column} ?? null;
            $field = $form->field($column);
            $cells[$column] = $field === null
                ? (\is_bool($value) ? $this->transformer->toDisplay('static', $value) : $value)
                : $this->transformer->toDisplay($field->type, $value, $field->jalaliFormatValue());
        }

        return $cells;
    }

    /** @return array<string, string> */
    private function currentFilters(Request $request, Resource $resource): array
    {
        $current = [];
        foreach (\array_keys($resource->filters()) as $column) {
            $value = $request->query((string) $column);
            $current[(string) $column] = $value === null ? '' : (string) $value;
        }

        return $current;
    }

    /** @return list<\Tavaan\Engine\Admin\Action> */
    private function allowedActions(Resource $resource, bool $bulk): array
    {
        return \array_values(\array_filter(
            $resource->actionList(),
            fn (\Tavaan\Engine\Admin\Action $action): bool => $action->bulk === $bulk && $action->isAllowed($this->gate),
        ));
    }

    /**
     * گزینه‌های فیلد relation برای فرم (id → display).
     *
     * @return array<string, string>
     */
    private function relationOptions(Field $field): array
    {
        $model = $field->relationModel();
        if ($model === null || !\class_exists($model)) {
            return [];
        }

        $options = [];
        foreach ($model::query()->limit(500)->get() as $item) {
            $key = (string) ($item->getKey() ?? '');
            $display = $item->{$field->relationDisplay()} ?? $key;
            $options[$key] = \is_scalar($display) ? (string) $display : $key;
        }

        return $options;
    }

    private function formResponse(Request $request, Resource $resource, ?Model $model): Response
    {
        $form = $resource->buildForm();
        $session = $request->hasSession() ? $request->session() : null;

        $values = [];
        foreach ($form->fields() as $field) {
            $old = $session?->getOldInput($field->name);
            if ($old !== null) {
                $values[$field->name] = $old;
                continue;
            }
            $raw = $model?->{$field->name} ?? $field->defaultValue();
            $values[$field->name] = $this->transformer->toDisplay($field->type, $raw, $field->jalaliFormatValue());
        }

        $relationChoices = [];
        foreach ($form->fields() as $field) {
            if ($field->type === 'relation') {
                $relationChoices[$field->name] = $this->relationOptions($field);
            }
        }

        return $this->html('admin::form', [
            'resource' => $resource,
            'form' => $form,
            'model' => $model,
            'values' => $values,
            'relationChoices' => $relationChoices,
            'errors' => (array) ($session?->get('errors') ?? []),
            'menu' => $this->menuItems(),
            'adminPrefix' => $this->admin->prefix(),
            'token' => $this->token($request),
            'appTitle' => $this->appTitle(),
        ]);
    }

    /** @return list<array{label: string, url: string, icon: string, active: bool}> */
    private function menuItems(): array
    {
        $items = [];
        foreach ($this->admin->menu()->visible($this->gate) as $item) {
            $url = $item->url;
            if ($url === '' && $item->route !== '') {
                try {
                    $url = \route($item->route);
                } catch (\Throwable) {
                    $url = '#';
                }
            }
            $items[] = ['label' => $item->label, 'url' => $url, 'icon' => $item->icon, 'active' => false];
        }

        return $items;
    }

    /**
     * مجوزهای عملیاتی کاربر برای نمایش/عدم‌نمایش دکمه‌ها (اجرا همچنان در
     * متدها authorize می‌شود — UI-only).
     *
     * @return array<string, bool>
     */
    private function permissions(Resource $resource): array
    {
        return [
            'view' => $this->gate->allows($resource->permissionFor('view')),
            'create' => $this->gate->allows($resource->permissionFor('create')),
            'update' => $this->gate->allows($resource->permissionFor('update')),
            'delete' => $this->gate->allows($resource->permissionFor('delete')),
        ];
    }

    private function appTitle(): string
    {
        return $this->app->bound('config')
            ? (string) $this->app->make('config')->get('app.name', 'تَوان')
            : 'تَوان';
    }

    private function token(Request $request): string
    {
        $session = $request->hasSession() ? $request->session() : null;

        return (string) ($session?->token() ?? '');
    }

    /** @param array<string, mixed> $data */
    private function html(string $template, array $data): Response
    {
        /** @var \Tavaan\Engine\View\ViewFactory $views */
        $views = $this->app->make('view');

        return new Response($views->render($template, $data));
    }

    private function redirectToIndex(Resource $resource): Response
    {
        /** @var Redirector $redirector */
        $redirector = $this->app->make('redirect');

        return $redirector->to('/' . $this->admin->prefix() . '/' . $resource->key());
    }

    private function back(Request $request): Response
    {
        unset($request);
        /** @var Redirector $redirector */
        $redirector = $this->app->make('redirect');

        return $redirector->back(fallback: '/' . $this->admin->prefix());
    }

    /**
     * validation شکست خورد — بازگشت با errors (flash در validatedData انجام شد).
     */
    private function backWithErrors(Request $request): Response
    {
        return $this->back($request);
    }

    private function config(string $key, mixed $default): mixed
    {
        return $this->app->bound('config') ? $this->app->make('config')->get('admin.' . $key, $default) : $default;
    }
}

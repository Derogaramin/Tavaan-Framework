<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin;

use Tavaan\Engine\Admin\Form\Form;
use Tavaan\Support\Str;

/**
 * تَوان | Tavaan — Admin
 * Resource — تعریف declarative یک CRUD مدیریتی (DOC-078 §2).
 *
 * از Model ساخته می‌شود و ۷+ route استاندارد (§3) با محافظت
 * Policy/Permission و Validation خودکار تولید می‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class Resource
{
    private string $title = '';

    private string $icon = '📄';

    private ?string $permission = null;

    private ?string $uri = null;

    /** @var list<string> */
    private array $columns = [];

    /** @var list<string> */
    private array $searchable = [];

    /** @var array<string, string> name → filter type (select|relation|text) */
    private array $filterable = [];

    /** @var list<string> */
    private array $sortable = [];

    private ?int $perPage = null;

    private ?Form $form = null;

    /** @var list<Action> */
    private array $actions = [];

    private ?Show $show = null;

    /** @var \Closure|null customizer کوئری فهرست (fn(ModelQueryBuilder $q): void) */
    private ?\Closure $queryCustomizer = null;

    private int $menuOrder = 100;

    /** @param class-string<\Tavaan\Engine\Database\ORM\Model> $model */
    public function __construct(public readonly string $model)
    {
        $this->title = Str::plural($this->modelName());
    }

    // ─── Fluent API ───────────────────────────────────────────────────────

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /** مجوز پایه → `<permission>.view|create|update|delete` (§2). */
    public function permission(string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    public function uri(string $uri): self
    {
        $this->uri = \trim($uri, '/');

        return $this;
    }

    /** @param list<string> $columns */
    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /** @param list<string> $fields */
    public function searchable(array $fields): self
    {
        $this->searchable = $fields;

        return $this;
    }

    /** @param array<string, string> $filters */
    public function filterable(array $filters): self
    {
        $this->filterable = $filters;

        return $this;
    }

    /** @param list<string> $columns */
    public function sortable(array $columns): self
    {
        $this->sortable = $columns;

        return $this;
    }

    public function perPage(int $perPage): self
    {
        $this->perPage = \max(1, $perPage);

        return $this;
    }

    /** @param \Closure(Form): void $configure */
    public function form(\Closure $configure): self
    {
        $form = new Form();
        $configure($form);
        $this->form = $form;

        return $this;
    }

    /** @param list<Action> $actions */
    public function actions(array $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    /** @param \Closure(Show): void $configure */
    public function show(\Closure $configure): self
    {
        $show = new Show();
        $configure($show);
        $this->show = $show;

        return $this;
    }

    /** @param \Closure(object): void $customizer */
    public function query(\Closure $customizer): self
    {
        $this->queryCustomizer = $customizer;

        return $this;
    }

    public function menuOrder(int $order): self
    {
        $this->menuOrder = $order;

        return $this;
    }

    // ─── computed ─────────────────────────────────────────────────────────

    /** @return class-string<\Tavaan\Engine\Database\ORM\Model> */
    public function modelClass(): string
    {
        if (!\class_exists($this->model)) {
            throw new \InvalidArgumentException(\sprintf('Admin resource model [%s] does not exist.', $this->model));
        }

        return $this->model;
    }

    public function modelName(): string
    {
        $parts = \explode('\\', $this->model);

        return (string) \end($parts);
    }

    /** کلید/segment URI (kebab جمع — `Product` → `products`). */
    public function key(): string
    {
        return $this->uri ?? Str::kebab(Str::plural($this->modelName()));
    }

    public function titleText(): string
    {
        return $this->title;
    }

    public function iconGlyph(): string
    {
        return $this->icon;
    }

    public function permissionFor(string $operation): string
    {
        $base = $this->permission ?? 'admin.' . $this->key();

        return $base . '.' . $operation;
    }

    /** @return list<string> */
    public function columnList(): array
    {
        return $this->columns;
    }

    /** @return list<string> */
    public function searchableFields(): array
    {
        return $this->searchable;
    }

    /** @return array<string, string> */
    public function filters(): array
    {
        return $this->filterable;
    }

    /** @return list<string> */
    public function sortableColumns(): array
    {
        return $this->sortable;
    }

    public function perPageCount(int $configDefault): int
    {
        return $this->perPage ?? $configDefault;
    }

    /** فرم (تنبل) — بدون تعریف، فرم خالی = فقط کلید اصلی. */
    public function buildForm(): Form
    {
        return $this->form ??= new Form();
    }

    /** @return list<Action> */
    public function actionList(): array
    {
        return $this->actions;
    }

    public function action(string $name, bool $bulk): ?Action
    {
        foreach ($this->actions as $action) {
            if ($action->name === $name && $action->bulk === $bulk) {
                return $action;
            }
        }

        return null;
    }

    public function showDefinition(): Show
    {
        return $this->show ??= new Show();
    }

    public function queryCustomizer(): ?\Closure
    {
        return $this->queryCustomizer;
    }

    public function order(): int
    {
        return $this->menuOrder;
    }
}

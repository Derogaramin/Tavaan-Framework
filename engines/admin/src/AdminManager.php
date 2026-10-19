<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Admin\Dashboard\Dashboard;
use Tavaan\Engine\Admin\Menu\Menu;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;
use Tavaan\Engine\Http\Routing\Router;

/**
 * تَوان | Tavaan — Admin
 * AdminManager — registry منابع/منو/داشبورد + ثبت routeهای CRUD (DOC-078).
 *
 * routeها به‌صورت **generic** ثبت می‌شوند (یک‌بار برای همهٔ منابع):
 * ```
 * GET    /{prefix}                              → dashboard
 * GET    /{prefix}/{resourceKey}                → index
 * GET    /{prefix}/{resourceKey}/create         → create
 * POST   /{prefix}/{resourceKey}                → store
 * GET    /{prefix}/{resourceKey}/{id}           → show
 * GET    /{prefix}/{resourceKey}/{id}/edit      → edit
 * PUT    /{prefix}/{resourceKey}/{id}           → update
 * DELETE /{prefix}/{resourceKey}/{id}           → destroy
 * POST   /{prefix}/{resourceKey}/{id}/actions/{action} → runAction
 * POST   /{prefix}/{resourceKey}/actions/{action}      → runBulkAction
 * ```
 *
 * 🔒 همه پشت AuthenticateMiddleware + بررسی Gate در کنترلر (AC-ADM-02).
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class AdminManager
{
    /** @var array<string, Resource> key → resource */
    private array $resources = [];

    private ?Menu $menu = null;

    private ?Dashboard $dashboard = null;

    private bool $routesRegistered = false;

    /** @var array<string, true> resourceهایی که آیتم منویشان اضافه شده */
    private array $menuResources = [];

    /** @param array<string, mixed> $config کلیدهای admin.* */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $config = [],
    ) {
    }

    // ─── تعریف منابع ──────────────────────────────────────────────────────

    /**
     * تعریف یک Resource مدیریتی (AC-ADM-01).
     *
     * @param class-string<\Tavaan\Engine\Database\ORM\Model> $model
     * @param \Closure(Resource): void|null                   $configure
     */
    public function resource(string $model, ?\Closure $configure = null): Resource
    {
        $resource = new Resource($model);
        if ($configure !== null) {
            $configure($resource);
        }

        $key = $resource->key();
        if (isset($this->resources[$key]) && $this->resources[$key]->model !== $model) {
            throw new \LogicException(\sprintf('Admin resource key [%s] is already registered for another model.', $key));
        }
        $this->resources[$key] = $resource;

        return $resource;
    }

    /** @return array<string, Resource> */
    public function resources(): array
    {
        return $this->resources;
    }

    public function find(string $key): ?Resource
    {
        return $this->resources[$key] ?? null;
    }

    // ─── منو و داشبورد ────────────────────────────────────────────────────

    /** `Admin::menu(fn(Menu $m) => …)` یا دسترسی به نمونهٔ منو. */
    public function menu(?\Closure $configure = null): Menu
    {
        $menu = $this->menu ??= new Menu();
        if ($configure !== null) {
            $configure($menu);
        }

        return $menu;
    }

    /** `Admin::dashboard(fn(Dashboard $d) => …)` یا دسترسی به نمونه. */
    public function dashboard(?\Closure $configure = null): Dashboard
    {
        $dashboard = $this->dashboard ??= new Dashboard();
        if ($configure !== null) {
            $configure($dashboard);
        }

        return $dashboard;
    }

    /** @param class-string $class @param array<string,mixed> $params */
    public function widget(string $class, array $params = [], int $width = 1): void
    {
        $this->dashboard()->widget($class, $params, $width);
    }

    // ─── routeها ──────────────────────────────────────────────────────────

    public function prefix(): string
    {
        return \trim((string) ($this->config['prefix'] ?? 'admin'), '/');
    }

    public function guard(): ?string
    {
        $guard = $this->config['guard'] ?? 'web';

        return \is_string($guard) && $guard !== '' ? $guard : null;
    }

    /**
     * ثبت routeهای پیشخوان + CRUD عمومی (idempotent) و افزودن منوی resourceها.
     *
     * routeها generic هستند (یک‌بار ثبت)؛ اما resourceها ممکن است پس از بوت
     * تعریف شوند → آیتم منوی هر resource فقط یک‌بار اضافه می‌شود.
     */
    public function registerRoutes(Router $router): void
    {
        if (!$this->routesRegistered) {
            $this->routesRegistered = true;

            $prefix = '/' . $this->prefix();
            $guard = $this->guard();
            $middleware = $guard === null
                ? AuthenticateMiddleware::class
                : AuthenticateMiddleware::class . ':' . $guard;

            $controller = Http\AdminController::class;

            $router->get($prefix, [Http\DashboardController::class, 'index'])->name('admin.dashboard')->middleware($middleware);
            $router->get($prefix . '/{resourceKey}', [$controller, 'index'])->name('admin.index')->middleware($middleware);
            $router->get($prefix . '/{resourceKey}/create', [$controller, 'create'])->name('admin.create')->middleware($middleware);
            $router->post($prefix . '/{resourceKey}', [$controller, 'store'])->name('admin.store')->middleware($middleware);
            $router->get($prefix . '/{resourceKey}/{id}', [$controller, 'show'])->name('admin.show')->middleware($middleware);
            $router->get($prefix . '/{resourceKey}/{id}/edit', [$controller, 'edit'])->name('admin.edit')->middleware($middleware);
            $router->put($prefix . '/{resourceKey}/{id}', [$controller, 'update'])->name('admin.update')->middleware($middleware);
            $router->delete($prefix . '/{resourceKey}/{id}', [$controller, 'destroy'])->name('admin.destroy')->middleware($middleware);
            $router->post($prefix . '/{resourceKey}/{id}/actions/{action}', [$controller, 'runAction'])->name('admin.action')->middleware($middleware);
            $router->post($prefix . '/{resourceKey}/actions/{action}', [$controller, 'runBulkAction'])->name('admin.bulk')->middleware($middleware);

            $this->menu()->add(
                (string) ($this->config['menu']['dashboard_label'] ?? 'داشبورد'),
                route: 'admin.dashboard',
                icon: '🏠',
                order: 0,
            );
        }

        // منوی خودکار از resourceها (§4) — حتی اگر پس از بوت تعریف شده باشند
        $prefix = '/' . $this->prefix();
        foreach ($this->resources as $key => $resource) {
            if (isset($this->menuResources[$key])) {
                continue;
            }
            $this->menuResources[$key] = true;
            $this->menu()->add(
                $resource->titleText(),
                url: $prefix . '/' . $resource->key(),
                icon: $resource->iconGlyph(),
                order: $resource->order(),
                permission: $resource->permissionFor('view'),
            );
        }
    }

    public function routesRegistered(): bool
    {
        return $this->routesRegistered;
    }
}

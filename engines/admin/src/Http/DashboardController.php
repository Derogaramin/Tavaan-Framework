<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Http;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Admin\AdminManager;
use Tavaan\Engine\Admin\Support\ChartRenderer;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Rbac\Gate;

/**
 * تَوان | Tavaan — Admin
 * DashboardController — صفحهٔ اول پیشخوان (ویجت‌ها + نمودارها — DOC-078 §5).
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class DashboardController
{
    public function __construct(private readonly ContainerInterface $app)
    {
    }

    public function index(Request $request): Response
    {
        /** @var AdminManager $admin */
        $admin = $this->app->make(AdminManager::class);
        /** @var Gate $gate */
        $gate = $this->app->make(Gate::class);

        $dashboard = $admin->dashboard();

        $menu = [];
        foreach ($admin->menu()->visible($gate) as $item) {
            $url = $item->url;
            if ($url === '' && $item->route !== '') {
                try {
                    $url = \route($item->route);
                } catch (\Throwable) {
                    $url = '#';
                }
            }
            $menu[] = ['label' => $item->label, 'url' => $url, 'icon' => $item->icon];
        }

        $charts = [];
        foreach ($dashboard->resolvedCharts() as $chart) {
            $charts[] = [
                'label' => $chart['label'],
                'type' => $chart['type'],
                'svg' => ChartRenderer::render($chart['data'], $chart['type']),
            ];
        }

        /** @var \Tavaan\Engine\View\ViewFactory $views */
        $views = $this->app->make('view');
        $session = $request->hasSession() ? $request->session() : null;

        return new Response($views->render('admin::dashboard', [
            'widgets' => $dashboard->renderWidgets($this->app),
            'charts' => $charts,
            'menu' => $menu,
            'adminPrefix' => $admin->prefix(),
            'user' => $this->currentUser(),
            'token' => (string) ($session?->token() ?? ''),
            'appTitle' => (string) ($this->app->bound('config') ? $this->app->make('config')->get('app.name', 'تَوان') : 'تَوان'),
        ]));
    }

    private function currentUser(): ?string
    {
        if (!$this->app->bound('auth')) {
            return null;
        }
        $user = $this->app->make('auth')->guard()->user();

        return $user === null ? null : (string) ($user->getAuthIdentifier() ?? '');
    }
}

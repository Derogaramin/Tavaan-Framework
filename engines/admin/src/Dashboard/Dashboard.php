<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Dashboard;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Support\Renderable;

/**
 * تَوان | Tavaan — Admin
 * Dashboard — ویجت‌ها و نمودارهای صفحهٔ اول پیشخوان (DOC-078 §5).
 *
 * Widgetها هر کلاس `Renderable` هستند (HMVC از ماژول‌ها نیز مجاز است) و
 * خطای هر ویجت ایزوله می‌شود (پیشخوان نمی‌شکند).
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class Dashboard
{
    /** @var list<array{class: string, params: array<string, mixed>, width: int}> */
    private array $widgets = [];

    /** @var list<array{name: string, label: string, type: string, data: array<array-key, mixed>|\Closure(): array<array-key, mixed>}> */
    private array $charts = [];

    /**
     * @param class-string        $class   کلاس Renderable
     * @param array<string,mixed> $params
     */
    public function widget(string $class, array $params = [], int $width = 1): self
    {
        $this->widgets[] = ['class' => $class, 'params' => $params, 'width' => \max(1, $width)];

        return $this;
    }

    /**
     * @param array<array-key, mixed>|\Closure(): array<array-key, mixed> $data
     */
    public function chart(string $name, array|\Closure $data, string $type = 'line', string $label = ''): self
    {
        $this->charts[] = [
            'name' => $name,
            'label' => $label !== '' ? $label : $name,
            'type' => \in_array($type, ['line', 'bar'], true) ? $type : 'line',
            'data' => $data,
        ];

        return $this;
    }

    /** @return list<array{class: string, params: array<string, mixed>, width: int}> */
    public function widgets(): array
    {
        return $this->widgets;
    }

    /**
     * دادهٔ ارزیابی‌شدهٔ نمودارها برای view.
     *
     * @return list<array{name: string, label: string, type: string, data: array<array-key, mixed>}>
     */
    public function resolvedCharts(): array
    {
        $charts = [];
        foreach ($this->charts as $chart) {
            $data = $chart['data'] instanceof \Closure ? ($chart['data'])() : $chart['data'];
            $charts[] = [
                'name' => $chart['name'],
                'label' => $chart['label'],
                'type' => $chart['type'],
                'data' => \is_array($data) ? $data : [],
            ];
        }

        return $charts;
    }

    /**
     * رندر همهٔ ویجت‌ها (خطا ایزوله → پیام خطا به‌جای crash).
     *
     * @return list<array{class: string, width: int, html: string, error: ?string}>
     */
    public function renderWidgets(ContainerInterface $container): array
    {
        $rendered = [];
        foreach ($this->widgets as $widget) {
            $entry = ['class' => $widget['class'], 'width' => $widget['width'], 'html' => '', 'error' => null];
            try {
                $instance = $container->make($widget['class'], $widget['params']);
                if (!$instance instanceof Renderable) {
                    throw new \LogicException('widget must implement Renderable');
                }
                $html = $instance->render();
                $entry['html'] = \is_string($html) ? $html : (string) $html;
            } catch (\Throwable $e) {
                $entry['error'] = $e->getMessage();
            }
            $rendered[] = $entry;
        }

        return $rendered;
    }
}

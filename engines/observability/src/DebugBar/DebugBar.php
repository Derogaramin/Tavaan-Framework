<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\DebugBar;

use Tavaan\Core\Contracts\Container\ContainerInterface;

/**
 * تَوان | Tavaan — Observability
 * DebugBar — پنل اشکال‌زدایی تزریق‌شده در پاسخ HTML (فقط dev).
 *
 * 🔒 فقط وقتی `app.debug=true` و env غیر production و config فعال باشد.
 * 🔒 همهٔ مقادیر با htmlspecialchars escape می‌شوند (XSS).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class DebugBar
{
    private const MAX_ROWS = 100;

    /** @var list<array{name: string, ms: float}> */
    private array $spans = [];

    /**
     * @param array<string, mixed> $config کل app config (برای debug/env)
     * @param array<string, mixed> $barConfig کلید debug_bar در observability
     */
    public function __construct(
        private readonly array $config,
        private readonly array $barConfig,
        private readonly ?Stopwatch $stopwatch = null,
        private readonly ?ContainerInterface $container = null,
        private readonly ?object $tracer = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['debug'] ?? false)
            && (string) ($this->config['env'] ?? 'production') !== 'production'
            && (bool) ($this->barConfig['enabled'] ?? false);
    }

    /** ثبت دستی یک بازه (مثلاً از listenerها). */
    public function addSpan(string $name, float $ms): void
    {
        if (\count($this->spans) < self::MAX_ROWS) {
            $this->spans[] = ['name' => $name, 'ms' => $ms];
        }
    }

    /**
     * جمع‌آوری داده‌های پنل.
     *
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return [
            'timings' => $this->stopwatch?->all() ?? [],
            'spans' => $this->spans,
            'queries' => $this->queries(),
            'memory' => [
                'peak_mb' => \round(\memory_get_peak_usage(true) / 1048576, 2),
                'current_mb' => \round(\memory_get_usage(true) / 1048576, 2),
            ],
            'events' => $this->events(),
            'route' => $this->route(),
            'middleware' => $this->middleware(),
            'config' => $this->configSummary(),
            'php' => ['version' => \PHP_VERSION, 'sapi' => \PHP_SAPI],
            'tavaan' => \defined('TVN_VERSION') ? (string) \TVN_VERSION : 'dev',
        ];
    }

    /** رندر HTML پنل (inline CSS، escape شده). */
    public function render(): string
    {
        $data = $this->collect();
        $e = static fn (mixed $v): string => \htmlspecialchars(
            (\is_scalar($v) || $v === null)
                ? (string) $v
                : (string) (\json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );

        $rows = '';

        // زمان‌ها
        $timings = (array) $data['timings'];
        foreach ($timings as $name => $ms) {
            $rows .= '<tr><td>' . $e($name) . '</td><td class="n">' . $e($ms) . ' ms</td></tr>';
        }
        foreach ((array) $data['spans'] as $span) {
            $rows .= '<tr><td>span: ' . $e($span['name']) . '</td><td class="n">' . $e(\round((float) $span['ms'], 3)) . ' ms</td></tr>';
        }

        // کوئری‌ها
        foreach ((array) $data['queries'] as $q) {
            $sql = $e($q['sql'] ?? '');
            $bindings = $e($q['bindings'] ?? []);
            $rows .= '<tr><td title="bindings: ' . $bindings . '">' . $sql . '</td><td class="n">' . $e($q['time_ms'] ?? 0) . ' ms</td></tr>';
        }

        // رویدادها
        $events = (array) $data['events'];
        $eventList = $events === [] ? '—' : $e(\implode(', ', \array_map(
            static fn (array $ev): string => (string) ($ev['name'] ?? '?'),
            \array_slice($events, 0, 30),
        )));

        $route = (array) $data['route'];
        $memory = (array) $data['memory'];
        $middleware = (array) $data['middleware'];
        $php = (array) $data['php'];

        return '<div id="tvn-debugbar" dir="ltr" style="position:fixed;bottom:0;left:0;right:0;z-index:99999;'
            . 'background:#1e1e2e;color:#cdd6f4;font:12px/1.6 monospace;border-top:2px solid #89b4fa;'
            . 'max-height:40vh;overflow:auto;padding:8px 12px">'
            . '<strong style="color:#89b4fa">Tavaan Debug Bar</strong> '
            . '<span>v' . $e($data['tavaan']) . '</span> · '
            . '<span>PHP ' . $e($php['version']) . ' (' . $e($php['sapi']) . ')</span> · '
            . '<span>route: <b>' . $e($route['name'] ?? '—') . '</b> ' . $e($route['method'] ?? '') . ' ' . $e($route['uri'] ?? '') . '</span> · '
            . '<span>memory: <b>' . $e($memory['peak_mb']) . ' MB</b> peak</span> · '
            . '<span>middleware: ' . $e(\implode(', ', $middleware) ?: '—') . '</span>'
            . '<div style="margin-top:4px"><b>events:</b> ' . $eventList . '</div>'
            . ($rows === '' ? '' : '<table style="width:100%;border-collapse:collapse;margin-top:6px">'
                . '<tr style="color:#a6adc8;text-align:left"><th style="padding:2px 6px">phase / query</th><th style="padding:2px 6px">time</th></tr>'
                . $rows . '</table>')
            . '</div>';
    }

    /** @return list<array<string, mixed>> */
    private function queries(): array
    {
        try {
            if ($this->container === null || !$this->container->bound('db')) {
                return [];
            }
            $manager = $this->container->make('db');
            if (!\method_exists($manager, 'connection')) {
                return [];
            }
            $connection = $manager->connection();
            if (!\method_exists($connection, 'queryLog')) {
                return [];
            }

            return \array_slice((array) $connection->queryLog(), -self::MAX_ROWS);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private function events(): array
    {
        try {
            if ($this->container === null || !$this->container->bound('events')) {
                return [];
            }
            $dispatcher = $this->container->make('events');
            if (!\method_exists($dispatcher, 'dispatchLog')) {
                return [];
            }

            return \array_slice((array) $dispatcher->dispatchLog(), -self::MAX_ROWS);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    private function route(): array
    {
        try {
            if ($this->container === null || !$this->container->bound('router')) {
                return [];
            }
            $router = $this->container->make('router');
            $match = \method_exists($router, 'current') ? $router->current() : null;
            if ($match === null) {
                return [];
            }
            $route = $match->route ?? null;

            return [
                'name' => $route !== null && \method_exists($route, 'getName') ? ($route->getName() ?: '—') : '—',
                'uri' => $route !== null && \method_exists($route, 'uri') ? $route->uri() : '',
                'method' => $route !== null && \method_exists($route, 'methods') ? \implode('|', (array) $route->methods()) : '',
                'parameters' => \method_exists($match, 'allParameters') ? $match->allParameters() : [],
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<string> */
    private function middleware(): array
    {
        try {
            if ($this->container === null || !$this->container->bound(\Tavaan\Engine\Http\HttpKernel::class)) {
                return [];
            }
            $kernel = $this->container->make(\Tavaan\Engine\Http\HttpKernel::class);
            if (!\method_exists($kernel, 'resolvedMiddleware')) {
                return [];
            }
            $out = [];
            foreach ((array) $kernel->resolvedMiddleware() as $mw) {
                $out[] = \is_object($mw) ? (new \ReflectionClass($mw))->getShortName() : (string) $mw;
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed> خلاصهٔ masked پیکربندی */
    private function configSummary(): array
    {
        try {
            if ($this->container === null || !$this->container->bound('config')) {
                return [];
            }
            $config = $this->container->make('config');
            if (\method_exists($config, 'masked')) {
                $all = (array) $config->masked();

                return \array_intersect_key($all, \array_flip(['app', 'database', 'cache', 'queue', 'session']));
            }

            return [];
        } catch (\Throwable) {
            return [];
        }
    }
}

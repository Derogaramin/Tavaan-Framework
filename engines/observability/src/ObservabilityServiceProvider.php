<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Http\Routing\Router;
use Tavaan\Engine\Observability\DebugBar\DebugBar;
use Tavaan\Engine\Observability\DebugBar\DebugBarMiddleware;
use Tavaan\Engine\Observability\DebugBar\Stopwatch;
use Tavaan\Engine\Observability\Health\CheckResult;
use Tavaan\Engine\Observability\Health\HealthManager;
use Tavaan\Engine\Observability\Http\MetricsMiddleware;
use Tavaan\Engine\Observability\Metrics\MetricsRegistry;
use Tavaan\Engine\Observability\Tracing\Tracer;

/**
 * تَوان | Tavaan — Observability
 * ObservabilityServiceProvider — ثبت Metrics/Tracer/Health/DebugBar (DOC-067).
 *
 * - `metrics` → MetricsRegistry (Facade: Metrics)
 * - `tracer` → Tracer (Facade: Trace)
 * - `health` → HealthManager
 * - `debugbar` → DebugBar
 * - `stopwatch` → Stopwatch
 *
 * در boot: ثبت routeهای /metrics و /health/*، افزودن middlewareهای سنجه/دیباگ
 * به زنجیرهٔ global (فقط وقتی فعال باشند) و flush کردن tracer در پایان درخواست.
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Stopwatch::class);
        $this->app->alias(Stopwatch::class, 'stopwatch');

        $this->app->singleton(MetricsRegistry::class, static function (ContainerInterface $c): MetricsRegistry {
            $config = $c->bound('config') ? (array) $c->make('config')->get('observability.metrics', []) : [];
            $cache = null;
            if (($config['persist'] ?? false)) {
                if ($c->bound(CacheRepository::class)) {
                    $candidate = $c->make(CacheRepository::class);
                    $cache = $candidate instanceof CacheRepository ? $candidate : null;
                } elseif ($c->bound('cache')) {
                    $manager = $c->make('cache');
                    if ($manager instanceof CacheRepository) {
                        $cache = $manager;
                    } elseif (\method_exists($manager, 'repository')) {
                        $repo = $manager->repository();
                        $cache = $repo instanceof CacheRepository ? $repo : null;
                    }
                }
            }

            return new MetricsRegistry($cache instanceof CacheRepository ? $cache : null, $config);
        });
        $this->app->alias(MetricsRegistry::class, 'metrics');
        $this->app->alias(MetricsRegistry::class, \Tavaan\Core\Contracts\Observability\MetricsInterface::class);

        $this->app->singleton(Tracer::class, static function (ContainerInterface $c): Tracer {
            $config = $c->bound('config') ? (array) $c->make('config')->get('observability.tracing', []) : [];
            $logger = $c->bound(LoggerInterface::class) ? $c->make(LoggerInterface::class) : null;

            return new Tracer($config, $logger instanceof LoggerInterface ? $logger : null);
        });
        $this->app->alias(Tracer::class, 'tracer');

        $this->app->singleton(HealthManager::class);
        $this->app->alias(HealthManager::class, 'health');

        $this->app->singleton(DebugBar::class, static function (ContainerInterface $c): DebugBar {
            $appConfig = $c->bound('config') ? (array) $c->make('config')->get('app', []) : [];
            $barConfig = $c->bound('config') ? (array) $c->make('config')->get('observability.debug_bar', []) : [];
            $stopwatch = $c->bound(Stopwatch::class) ? $c->make(Stopwatch::class) : null;
            $tracer = $c->bound(Tracer::class) ? $c->make(Tracer::class) : null;

            return new DebugBar(
                $appConfig,
                $barConfig,
                $stopwatch instanceof Stopwatch ? $stopwatch : null,
                $c,
                \is_object($tracer) ? $tracer : null,
            );
        });
        $this->app->alias(DebugBar::class, 'debugbar');

        $this->app->singleton(DebugBarMiddleware::class, static fn (ContainerInterface $c): DebugBarMiddleware
            => new DebugBarMiddleware($c->make(DebugBar::class)));
        $this->app->singleton(MetricsMiddleware::class, static fn (ContainerInterface $c): MetricsMiddleware
            => new MetricsMiddleware($c));
    }

    public function boot(): void
    {
        if (!$this->app->bound('config')) {
            return;
        }

        /** @var \Tavaan\Core\Contracts\Config\RepositoryInterface $config */
        $config = $this->app->make('config');
        $obs = (array) $config->get('observability', []);

        $this->registerMiddleware($config, $obs);
        $this->registerRoutes($obs);
        $this->registerHealthChecks($obs);
        $this->registerFlushListener();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            MetricsRegistry::class, 'metrics',
            Tracer::class, 'tracer',
            HealthManager::class, 'health',
            DebugBar::class, 'debugbar',
            Stopwatch::class, 'stopwatch',
        ];
    }

    /**
     * افزودن MetricsMiddleware/DebugBarMiddleware به زنجیرهٔ global
     * (config http پیش از ساخت HttpKernel خوانده می‌شود — kernel تنبل است).
     *
     * @param array<string, mixed> $obs
     */
    private function registerMiddleware(\Tavaan\Core\Contracts\Config\RepositoryInterface $config, array $obs): void
    {
        $global = (array) $config->get('http.middleware.global', []);

        if ((bool) ($obs['metrics']['enabled'] ?? true) && (bool) ($obs['metrics']['http_middleware'] ?? true)) {
            if (!\in_array(MetricsMiddleware::class, $global, true)) {
                $global[] = MetricsMiddleware::class;
            }
        }

        $barEnabled = (bool) ($obs['debug_bar']['enabled'] ?? false)
            && (bool) $config->get('app.debug', false)
            && (string) $config->get('app.env', 'production') !== 'production';

        if ($barEnabled) {
            if (!\in_array(DebugBarMiddleware::class, $global, true)) {
                $global[] = DebugBarMiddleware::class;
            }
            $config->set('http.middleware.global', $global);

            // ضبط رویدادها برای پنل (DOC-067 §5)
            if ($this->app->bound('events')) {
                $dispatcher = $this->app->make('events');
                if (\method_exists($dispatcher, 'startRecording')) {
                    $dispatcher->startRecording((int) ($obs['debug_bar']['max_events'] ?? 500));
                }
            }

            return;
        }

        $config->set('http.middleware.global', $global);
    }

    /** @param array<string, mixed> $obs */
    private function registerRoutes(array $obs): void
    {
        if (!$this->app->bound('router')) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make('router');
        $app = $this->app;

        // GET /metrics — Prometheus text format
        if ((bool) ($obs['metrics']['enabled'] ?? true)) {
            $endpoint = \trim((string) ($obs['metrics']['endpoint'] ?? '/metrics'), '/') ;
            $router->get('/' . $endpoint, static function () use ($app): Response {
                /** @var MetricsRegistry $metrics */
                $metrics = $app->make(MetricsRegistry::class);

                return new Response($metrics->collect(), 200, [
                    'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
                    'Cache-Control' => 'no-store',
                ]);
            })->name('observability.metrics');
        }

        // GET /health/live و /health/ready
        $healthBase = \rtrim((string) ($obs['health']['endpoint'] ?? '/health'), '/');
        if ($healthBase !== '') {
            $router->get($healthBase . '/live', static function () use ($app): Response {
                /** @var HealthManager $health */
                $health = $app->make(HealthManager::class);
                $result = $health->live();

                return Response::json($result, HealthManager::statusCode($result));
            })->name('observability.health.live');

            $router->get($healthBase . '/ready', static function () use ($app): Response {
                /** @var HealthManager $health */
                $health = $app->make(HealthManager::class);
                $result = $health->ready();

                return Response::json($result, HealthManager::statusCode($result));
            })->name('observability.health.ready');
        }
    }

    /**
     * ثبت بررسی‌های پیش‌فرض (database/cache/queue/disk) — فقط وقتی binding موجود باشد.
     *
     * @param array<string, mixed> $obs
     */
    private function registerHealthChecks(array $obs): void
    {
        /** @var HealthManager $health */
        $health = $this->app->make(HealthManager::class);
        $checks = (array) ($obs['health']['checks'] ?? []);
        $app = $this->app;

        if (\in_array('database', $checks, true) && $this->app->bound('db')) {
            $health->add('database', static function () use ($app): CheckResult|array {
                $connection = $app->make('db')->connection();
                $rows = $connection->select('SELECT 1 AS ok');

                return $rows !== [] ? ['status' => 'up'] : ['status' => 'down', 'message' => 'no result'];
            }, 'ready');
        }

        if (\in_array('cache', $checks, true) && ($this->app->bound('cache') || $this->app->bound(CacheRepository::class))) {
            $health->add('cache', static function () use ($app): array {
                $cache = $app->bound(CacheRepository::class) ? $app->make(CacheRepository::class) : $app->make('cache');
                $ok = $cache->put('tvn:health:ping', 1, 60) && $cache->get('tvn:health:ping') !== null;

                return $ok ? ['status' => 'up'] : ['status' => 'down', 'message' => 'cache roundtrip failed'];
            }, 'ready');
        }

        if (\in_array('queue', $checks, true) && $this->app->bound('queue')) {
            $max = (int) ($obs['health']['queue_max'] ?? 10000);
            $health->add('queue', static function () use ($app, $max): array {
                $queue = $app->make('queue');
                if (!\method_exists($queue, 'size')) {
                    return ['status' => 'up', 'message' => 'size unsupported'];
                }
                $size = (int) $queue->size();

                return $size < $max
                    ? ['status' => 'up', 'message' => 'size=' . $size]
                    : ['status' => 'down', 'message' => 'queue size ' . $size . ' >= ' . $max];
            }, 'ready');
        }

        if (\in_array('disk', $checks, true)) {
            $minBytes = (int) ($obs['health']['disk_min_bytes'] ?? 1073741824);
            $health->add('disk', static function () use ($minBytes): array {
                $base = \defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : '/';
                $free = @\disk_free_space($base);
                if ($free === false) {
                    return ['status' => 'down', 'message' => 'disk_free_space unavailable'];
                }

                return $free > $minBytes
                    ? ['status' => 'up', 'message' => \round($free / 1073741824, 1) . 'GB free']
                    : ['status' => 'down', 'message' => 'low disk space: ' . \round($free / 1048576) . 'MB'];
            }, 'ready');
        }
    }

    private function registerFlushListener(): void
    {
        if (!$this->app->bound('events')) {
            return;
        }

        $app = $this->app;
        $dispatcher = $this->app->make('events');
        if (!\method_exists($dispatcher, 'listen')) {
            return;
        }

        // flush کردن exporterهای tracing در پایان درخواست (OTLP batch و …)
        $dispatcher->listen('http.request.terminated', static function () use ($app): void {
            if ($app->bound(Tracer::class)) {
                try {
                    $app->make(Tracer::class)->flush();
                } catch (\Throwable) {
                    // best-effort
                }
            }
        });
    }
}

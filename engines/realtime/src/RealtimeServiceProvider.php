<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Realtime\Sse\SseStreamer;

/**
 * تَوان | Tavaan — Realtime
 * RealtimeServiceProvider — ثبت RealtimeManager (`realtime`/`broadcast`) + routeها.
 *
 * - `realtime` → RealtimeManager (Facade: Realtime/Broadcast)
 * - `realtime.presence` → PresenceManager
 * - `realtime.sse` → SseStreamer
 * - routeها: GET {sse.endpoint} (SSE)، POST {auth_endpoint} (احراز کانال)
 * - routes/channels.php در boot بارگذاری می‌شود
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class RealtimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelRegistry::class);

        $this->app->singleton(RealtimeManager::class, static fn (ContainerInterface $c): RealtimeManager
            => new RealtimeManager($c, $c->make(ChannelRegistry::class)));
        $this->app->alias(RealtimeManager::class, 'realtime');
        $this->app->alias(RealtimeManager::class, 'broadcast');

        $this->app->singleton(PresenceManager::class, static function (ContainerInterface $c): PresenceManager {
            $cache = null;
            if ($c->bound(CacheRepository::class)) {
                $candidate = $c->make(CacheRepository::class);
                $cache = $candidate instanceof CacheRepository ? $candidate : null;
            } elseif ($c->bound('cache')) {
                $manager = $c->make('cache');
                if ($manager instanceof CacheRepository) {
                    $cache = $manager;
                } elseif (\method_exists($manager, 'repository')) {
                    $cache = $manager->repository();
                }
            }
            $ttl = $c->bound('config') ? (int) $c->make('config')->get('realtime.presence.ttl', 90) : 90;

            return new PresenceManager($cache instanceof CacheRepository ? $cache : null, $ttl);
        });
        $this->app->alias(PresenceManager::class, 'realtime.presence');

        $this->app->singleton(SseStreamer::class, static function (ContainerInterface $c): SseStreamer {
            $config = $c->bound('config') ? (array) $c->make('config')->get('realtime.sse', []) : [];

            return new SseStreamer($c->make(RealtimeManager::class), $config);
        });
        $this->app->alias(SseStreamer::class, 'realtime.sse');

        $this->app->singleton(BroadcastingAuthController::class, static fn (ContainerInterface $c): BroadcastingAuthController
            => new BroadcastingAuthController(
                $c->make(RealtimeManager::class),
                $c->make(PresenceManager::class),
                $c,
            ));

        $this->app->singleton(RealtimeFacadeRoot::class, static fn (ContainerInterface $c): RealtimeFacadeRoot
            => new RealtimeFacadeRoot($c->make(RealtimeManager::class), $c->make(PresenceManager::class)));
        $this->app->alias(RealtimeFacadeRoot::class, 'realtime.facade');
    }

    public function boot(): void
    {
        $this->loadChannelsFile();
        $this->registerRoutes();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            RealtimeManager::class, 'realtime', 'broadcast',
            ChannelRegistry::class,
            PresenceManager::class, 'realtime.presence',
            SseStreamer::class, 'realtime.sse',
            BroadcastingAuthController::class,
            RealtimeFacadeRoot::class, 'realtime.facade',
        ];
    }

    /** بارگذاری routes/channels.php (مانند routes/console.php). */
    private function loadChannelsFile(): void
    {
        $file = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/routes/channels.php' : null;
        if ($file === null || !\is_file($file)) {
            return;
        }

        // 🔒 manager را پیش‌ساخت نمی‌کنیم (snapshot config زودهنگام می‌شود)؛
        // فایل از Facade یا lazy-make استفاده می‌کند.
        require $file;
    }

    private function registerRoutes(): void
    {
        if (!$this->app->bound('router') || !$this->app->bound('config')) {
            return;
        }

        /** @var \Tavaan\Engine\Http\Routing\Router $router */
        $router = $this->app->make('router');
        $config = (array) $this->app->make('config')->get('realtime', []);
        $app = $this->app;

        // SSE endpoint — GET /events/stream?channels=a,b
        $sseEndpoint = (string) ($config['sse']['endpoint'] ?? '/events/stream');
        $router->get($sseEndpoint, static function (Request $request) use ($app, $config): Response|\Tavaan\Engine\Http\StreamedResponse {
            /** @var RealtimeManager $manager */
            $manager = $app->make(RealtimeManager::class);
            /** @var SseStreamer $streamer */
            $streamer = $app->make(SseStreamer::class);

            $channels = [];
            foreach (\explode(',', (string) $request->query('channels', '')) as $channel) {
                $channel = \trim($channel);
                if ($channel === '') {
                    continue;
                }
                // 🔒 کانال خصوصی بدون احراز → رد
                if ($manager->isPrivate($channel) || $manager->isPresence($channel)) {
                    return Response::json(['error' => 'forbidden', 'channel' => $channel], 403);
                }
                $channels[] = $channel;
            }
            if ($channels === []) {
                return Response::json(['error' => 'channels_required'], 400);
            }

            $maxSeconds = (int) $request->query('max_seconds', 0);

            return $streamer->response($channels, maxSeconds: $maxSeconds);
        })->name('realtime.stream');

        // احراز کانال خصوصی — POST /broadcasting/auth
        $authEndpoint = (string) ($config['auth_endpoint'] ?? '/broadcasting/auth');
        $router->post($authEndpoint, static function (Request $request) use ($app): Response {
            /** @var BroadcastingAuthController $controller */
            $controller = $app->make(BroadcastingAuthController::class);

            return $controller->auth($request);
        })->name('realtime.auth');
    }
}

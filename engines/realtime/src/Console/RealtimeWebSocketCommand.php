<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Realtime\WebSocket\WebSocketServer;

/**
 * تَوان | Tavaan — Realtime
 * RealtimeWebSocketCommand — اجرای سرور WebSocket (DOC-045 §5).
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class RealtimeWebSocketCommand extends Command
{
    protected string $signature = 'realtime:websocket {--host=0.0.0.0 : آدرس گوش‌دادن} {--port=8081 : پورت} {--max-iterations= : تعداد تکرار حلقه (خالی = بی‌نهایت؛ تست)}';

    protected string $description = 'اجرای سرور WebSocket بلادرنگ (RFC 6455 خودنوشته)';

    public function handle(): int
    {
        $host = (string) ($this->option('host') ?? '0.0.0.0');
        $port = (int) ($this->option('port') ?? 8081);
        $maxIterations = $this->option('max-iterations');
        $maxIterations = $maxIterations === null || $maxIterations === '' ? null : \max(1, (int) $maxIterations);

        $server = new WebSocketServer(
            $this->app->make(\Tavaan\Engine\Realtime\RealtimeManager::class),
            (array) $this->app->make('config')->get('realtime.websocket', []),
        );

        $this->info(\sprintf('🔌 سرور WebSocket روی %s:%d …', $host, $port));
        $this->comment('برای توقف: Ctrl+C');

        if (\function_exists('pcntl_async_signals') && \function_exists('pcntl_signal')) {
            \pcntl_async_signals(true);
            \pcntl_signal(SIGINT, static fn () => true);   // exit gracefully via loop guard
            \pcntl_signal(SIGTERM, static fn () => true);
        }

        try {
            $server->run($host, $port, $maxIterations);
        } catch (\Throwable $e) {
            $this->error('خطای سرور: ' . $e->getMessage());
            $server->shutdown();

            return self::FAILURE;
        } finally {
            $server->shutdown();
        }

        return self::SUCCESS;
    }
}

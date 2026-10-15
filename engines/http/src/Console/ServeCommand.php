<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Console;

use Tavaan\Engine\Console\Command;

/**
 * سرور توسعه با Built-in PHP (AC-HTTP-06: `tvn serve`).
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class ServeCommand extends Command
{
    protected string $signature = 'serve {--host=127.0.0.1} {--port=8000}';

    protected string $description = 'اجرای سرور توسعه (php -S)';

    public function handle(): int
    {
        $host = (string) $this->option('host', '127.0.0.1');
        $port = (string) $this->option('port', '8000');
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : \getcwd();

        $this->newLine();
        $this->info("  🚀 Tavaan dev server: http://{$host}:{$port}");
        $this->comment('  برای توقف: Ctrl+C');
        $this->newLine();

        $cmd = \sprintf(
            '%s -S %s:%s -t %s %s',
            \escapeshellarg(PHP_BINARY),
            \escapeshellarg($host),
            \escapeshellarg($port),
            \escapeshellarg($base . '/public'),
            \escapeshellarg($base . '/bin/server-router.php'),
        );

        \passthru($cmd, $exitCode);

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}

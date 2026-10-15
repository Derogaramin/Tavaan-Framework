<?php

/**
 * تَوان | Tavaan — نمونهٔ Hello World (CLI)
 *
 * اجرا: php examples/hello-world/cli.php
 *
 * نمایش: bootstrap → ApplicationBuilder → Container (Auto-Wiring + Contextual)
 * → Event → Log → Pipeline.
 */

declare(strict_types=1);

use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Pipeline\Pipeline;

require \dirname(__DIR__, 2) . '/core/bootstrap.php';

$app = ApplicationBuilder::create(TVN_BASE_PATH)->forConsole()->boot();

// ── ۱) Auto-Wiring با Contextual Binding ─────────────────────────────────
interface Greeter
{
    public function greet(string $name): string;
}

$app->bind('greeter.fa', static fn (): Greeter => new class implements Greeter {
    public function greet(string $name): string
    {
        return "سلام {$name}! 🎉";
    }
});

/** @var DispatcherInterface $events */
$events = $app->make('events');

$events->listen('app.hello', static function (string $event, array $payload) use ($app): void {
    /** @var Greeter $greeter */
    $greeter = $app->make('greeter.fa');
    echo $greeter->greet((string) $payload['name']), PHP_EOL;
});

$events->dispatch('app.hello', ['name' => 'تَوان']);

// ── ۲) Pipeline (Middleware-like) ────────────────────────────────────────
$result = (new Pipeline($app))
    ->send('donya')
    ->through([
        static fn (string $p, Closure $next): string => $next(\strtoupper($p)),
        static fn (string $p, Closure $next): string => $next($p . '!'),
    ])
    ->then(static fn (string $p): string => "hello {$p}");

echo $result, PHP_EOL;

// ── ۳) Log ساختاریافته (کانال array — بدون نوشتن روی دیسک) ──────────────
$app->make('config')->set('log.default', 'array');
$app->make('log')->info('example.hello_world', ['boot_ms' => \round($app->bootTimeMs(), 2)]);
echo 'logged to array channel ✓', PHP_EOL;

$app->terminate();

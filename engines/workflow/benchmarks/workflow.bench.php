<?php

/**
 * تَوان | Tavaan — Workflow Benchmark (M7)
 *
 * اهداف داخلی (DOC-071 هدف عددی صریح ندارد — اینها سقف‌های کیفی‌اند):
 * - ساخت تعریف ۳۰ state/۶۰ transition   ≤ ۵ms
 * - fire (درایور array، بدون DB)          ≤ ۳۰۰µs
 * - start instance                        ≤ ۳۰۰µs
 *
 * اجرا: php engines/workflow/benchmarks/workflow.bench.php
 */

declare(strict_types=1);

require \dirname(__DIR__, 3) . '/core/bootstrap.php';

use Tavaan\Engine\Workflow\ProcessBuilder;
use Tavaan\Engine\Workflow\StateType;
use Tavaan\Engine\Workflow\Store\ArrayStore;
use Tavaan\Engine\Workflow\WorkflowManager;

$manager = new WorkflowManager(new ArrayStore());

// ── ساخت تعریف بزرگ (۳۰ state / ۶۰ transition) ───────────────────────────
$start = \hrtime(true);
$manager->define('bench-chain', function (ProcessBuilder $p): void {
    $p->start('s0');
    for ($i = 1; $i < 30; $i++) {
        $p->state('s' . $i, $i % 10 === 0 ? StateType::UserTask : StateType::Normal);
        $p->transition('t' . $i, from: 's' . ($i - 1), to: 's' . $i)
            ->guard(static fn ($c): bool => true);
    }
    $p->state('end', StateType::End);
    $p->transition('t30', from: 's29', to: 'end');
    // یال‌های اضافی برای حجم (guard رد)
    for ($i = 0; $i < 29; $i++) {
        $p->transition('x' . $i, from: 's' . $i, to: 's' . ($i + 1))
            ->guard(static fn ($c): bool => false);
    }
});
$buildMs = (\hrtime(true) - $start) / 1e6;
\printf("build definition (30 states)     %9.2f ms (target ≤ 5 ms) %s\n", $buildMs, $buildMs <= 5 ? '✅' : '❌');

// ── start ────────────────────────────────────────────────────────────────
$iterations = 2000;
$start = \hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $manager->start('bench-chain', 'order', (string) $i);
}
$startUs = (\hrtime(true) - $start) / 1000 / $iterations;
\printf("start instance                   %9.1f µs/op (target ≤ 300 µs) %s\n", $startUs, $startUs <= 300 ? '✅' : '❌');

// ── fire (۲۹ گذار متوالی روی یک instance) ────────────────────────────────
$fires = 0;
$start = \hrtime(true);
for ($i = 0; $i < 100; $i++) {
    $instance = $manager->start('bench-chain', 'order', 'fire-' . $i);
    for ($t = 1; $t <= 29; $t++) {
        $instance->fire('t' . $t);
        $fires++;
    }
}
$fireUs = (\hrtime(true) - $start) / 1000 / $fires;
\printf("fire transition                  %9.1f µs/op (target ≤ 300 µs) %s  [%d fires]\n",
    $fireUs, $fireUs <= 300 ? '✅' : '❌', $fires);

\printf("peak memory                      %9.1f MB\n", \memory_get_peak_usage(true) / 1048576);

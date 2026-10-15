<?php

/**
 * تَوان | Tavaan — Container Benchmark (DOC-031 §10)
 *
 * اجرا: php engines/container/benchmarks/container.bench.php
 */

declare(strict_types=1);

require \dirname(__DIR__, 3) . '/core/bootstrap.php';

use Tavaan\Engine\Container\Container;

interface BenchGreeter
{
    public function greet(): string;
}

final class BenchPersian implements BenchGreeter
{
    public function greet(): string
    {
        return 'سلام';
    }
}

final class BenchDep1
{
    public function __construct(public readonly BenchGreeter $g)
    {
    }
}
final class BenchDep2
{
    public function __construct(public readonly BenchDep1 $d)
    {
    }
}
final class BenchDep3
{
    public function __construct(public readonly BenchDep2 $d)
    {
    }
}
final class BenchDep4
{
    public function __construct(public readonly BenchDep3 $d)
    {
    }
}
final class BenchDep5
{
    public function __construct(
        public readonly BenchDep4 $d,
        public readonly BenchDep1 $d1,
        public readonly BenchDep2 $d2,
        public readonly BenchDep3 $d3,
        public readonly BenchGreeter $g,
    ) {
    }
}

function bench(string $label, callable $fn, float $targetUs): void
{
    $fn();   // warmup
    $start = \hrtime(true);
    $iterations = 10_000;
    $fn2 = $fn;
    for ($i = 0; $i < $iterations; $i++) {
        $fn2();
    }
    $usPerOp = (\hrtime(true) - $start) / 1000 / $iterations;
    $pass = $usPerOp <= $targetUs ? '✅' : '❌';
    \printf("%-42s %8.2f µs/op  (target ≤ %.0f µs) %s\n", $label, $usPerOp, $targetUs, $pass);
}

$c = new Container();
$c->singleton(BenchGreeter::class, BenchPersian::class);

bench('make() singleton (cached)', static fn () => $c->make(BenchGreeter::class), 1.0);
bench('make() transient (no deps)', static fn () => $c->make(BenchDep1::class, ['g' => new BenchPersian()]) && false ? 0 : 0, 2.0);

$c2 = new Container();
$c2->singleton(BenchGreeter::class, BenchPersian::class);
$c2->singleton(BenchDep1::class);
$c2->singleton(BenchDep2::class);
$c2->singleton(BenchDep3::class);
$c2->singleton(BenchDep4::class);
bench('make() 5-deps first (reflection)', static function () use ($c2): void {
    $c2->forgetInstance(BenchDep5::class);
    $c2->make(BenchDep5::class);
}, 300.0);

$c3 = new Container();
$c3->singleton(BenchGreeter::class, BenchPersian::class);
$c3->singleton(BenchDep1::class);
$c3->singleton(BenchDep2::class);
$c3->singleton(BenchDep3::class);
$c3->singleton(BenchDep4::class);
$c3->singleton(BenchDep5::class);
$c3->make(BenchDep5::class);
bench('make() 5-deps singleton hits', static fn () => $c3->make(BenchDep5::class), 1.0);

$service = new BenchPersian();
bench('call() method injection', static fn () => $c->call([$service, 'greet']), 25.0);

$start = \microtime(true);
for ($i = 0; $i < 1000; $i++) {
    $c->make(BenchGreeter::class);
}
$ms = (\microtime(true) - $start) * 1000;
\printf("%-42s %8.3f ms   (target ≤ 1 ms) %s\n", '1000 sequential make()', $ms, $ms <= 1.0 ? '✅' : '❌');

\printf("memory: %.2f MB\n", \memory_get_peak_usage(true) / 1048576);

<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — ORM
 * benchmarks/hydrate.bench.php — سنجش hydration (هدف DOC-050 §10: ۱۰۰۰ رکورد ≤ ۲۵ms).
 *
 * اجرا: php engines/orm/benchmarks/hydrate.bench.php
 */

namespace Tavaan\Engine\Orm\Benchmarks;

use Tavaan\Engine\Config\Repository;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\ModelQueryBuilder;
use Tavaan\Engine\Database\ORM\Support\Money;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\SchemaBuilder;

require \dirname(__DIR__, 3) . '/core/bootstrap.php';

final class BenchProduct extends Model
{
    protected string $table = 'bench_products';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['title', 'price', 'status', 'meta'];

    /** @var array<string, string> */
    protected array $casts = ['price' => 'money:IRR', 'meta' => 'array'];
}

$config = new Repository(['database' => [
    'default' => 'bench',
    'connections' => ['bench' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']],
]]);

$manager = new ConnectionManager($config);
Model::setConnectionResolver($manager);

$connection = $manager->connection();
$schema = new SchemaBuilder($manager);
$schema->create('bench_products', function (Blueprint $t): void {
    $t->id();
    $t->string('title');
    $t->integer('price')->default(0);
    $t->string('status')->default('active');
    $t->text('meta')->nullable();
    $t->timestamps(3);
});

$rows = [];
for ($i = 0; $i < 1000; $i++) {
    $rows[] = [
        'title' => 'محصول ' . $i,
        'price' => $i * 1000,
        'status' => 'active',
        'meta' => '{"i":' . $i . '}',
        'created_at' => '2026-01-01 00:00:00',
        'updated_at' => '2026-01-01 00:00:00',
    ];
}
foreach (\array_chunk($rows, 100) as $chunk) {
    $connection->table('bench_products')->insert($chunk);
}

// گرم‌کردن
BenchProduct::all();

$runs = 5;
$best = INF;
$total = 0.0;
for ($r = 0; $r < $runs; $r++) {
    $start = \microtime(true);
    /** @var list<BenchProduct> $models */
    $models = BenchProduct::all();
    $ms = (\microtime(true) - $start) * 1000;
    $total += $ms;
    $best = \min($best, $ms);
}

$memoryMb = \memory_get_peak_usage(true) / 1048576;

\printf("Hydrate 1000 records (with casts): best %.2fms avg %.2fms\n", $best, $total / $runs);
\printf("Models: %d | first price is Money: %s\n", \count($models), $models[0]->price instanceof Money ? 'yes' : 'NO');
\printf("Peak memory: %.1f MB\n", $memoryMb);
\printf("Target (DOC-050): <= 25ms → %s\n", $best <= 25 ? 'PASS ✅' : 'FAIL ❌');

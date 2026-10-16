<?php

/**
 * تَوان | Tavaan — View Benchmark (DOC-043 §11)
 *
 * اهداف: کامپایل قالب ۱۰۰ خطی ≤ ۳ms — رندر کامپایل‌شده ≤ ۰٫۵ms —
 * Escaper::html برای ۱KB ≤ ۵µs.
 *
 * اجرا: php engines/view/benchmarks/render.bench.php
 */

declare(strict_types=1);

require \dirname(__DIR__, 3) . '/core/bootstrap.php';

use Tavaan\Engine\View\Escaper;
use Tavaan\Engine\View\ViewFactory;

$base = \sys_get_temp_dir() . '/tvn-view-bench-' . \getmypid();
@\mkdir($base . '/views/layouts', 0775, true);
@\mkdir($base . '/compiled', 0775, true);

// قالب ~۱۰۰ خطی با directive/echo/component
$lines = ['@extends("layouts.base")', '@section("content")', '<div class="page">'];
for ($i = 0; $i < 30; $i++) {
    $lines[] = '  @if ($show' . $i . ')';
    $lines[] = '  <p class="row-' . $i . '">{{ $title }} — {{ $items[' . ($i % 5) . '] }}</p>';
    $lines[] = '  @endif';
}
$lines[] = '  @foreach ($items as $item)<li>{{ $item }}</li>@endforeach';
$lines[] = '</div>';
$lines[] = '@endsection';
\file_put_contents($base . '/views/big.tv.php', \implode("\n", $lines));
\file_put_contents($base . '/views/layouts/base.tv.php', "<!DOCTYPE html><html><body>@yield(\"content\")@stack(\"s\")</body></html>");

$factory = new ViewFactory(null, ['paths' => [$base . '/views'], 'compiled' => $base . '/compiled']);

$data = ['title' => 'عنوان آزمایش'];
for ($i = 0; $i < 30; $i++) {
    $data['show' . $i] = $i % 2 === 0;
    $data['i' . $i] = $i;
}
$data['items'] = ['یک', 'دو', 'سه', 'چهار', 'پنج'];

// compile
$start = \hrtime(true);
$factory->compileIfNeeded('big');
$compileMs = (\hrtime(true) - $start) / 1e6;
\printf("compile ~100-line template                 %9.3f ms (target ≤ 3 ms) %s\n", $compileMs, $compileMs <= 3 ? '✅' : '❌');

// render (warm)
$factory->render('big', $data);
$start = \hrtime(true);
$iterations = 500;
for ($i = 0; $i < $iterations; $i++) {
    $factory->render('big', $data);
}
$renderMs = (\hrtime(true) - $start) / 1e6 / $iterations;
\printf("render compiled (100 lines + layout)       %9.3f ms (target ≤ 0.5 ms) %s\n", $renderMs, $renderMs <= 0.5 ? '✅' : '❌');

// escaper — دو سناریو: متن معمول (fast path) و متن پرخشونه (worst case)
// هدف DOC-112 («Escaper::html برای ۱KB ≤ ۵µs») برای متن معمولی ۱KB است؛
// سناریوی hostile سنجهٔ اطلاع‌رسانی بدترین حالت است (هدف مرجع ≤ ۱۰µs).
$typical = \str_repeat('متن آزمایشی فارسی برای سنجش کارایی موتور ویو تَوان. ', 22);   // ~1KB بدون کاراکتر خاص
$hostile = \str_repeat('متن آزمایشی با <html> & "quotes" ', 30);                       // ~1KB با escape فراوان

foreach (['typical ~1KB' => [$typical, 5.0], 'hostile ~1KB (info)' => [$hostile, 10.0]] as $label => [$text, $target]) {
    $start = \hrtime(true);
    for ($i = 0; $i < 10000; $i++) {
        Escaper::html($text);
    }
    $escUs = (\hrtime(true) - $start) / 1000 / 10000;
    \printf("Escaper::html %-14s                %9.3f µs (target ≤ %.0f µs) %s\n", $label, $escUs, $target, $escUs <= $target ? '✅' : '⚠️');
}

// cleanup
foreach (\glob($base . '/compiled/*') ?: [] as $f) {
    @\unlink($f);
}
@\unlink($base . '/views/big.tv.php');
@\unlink($base . '/views/layouts/base.tv.php');
@\rmdir($base . '/views/layouts');
@\rmdir($base . '/views');
@\rmdir($base . '/compiled');
@\rmdir($base);

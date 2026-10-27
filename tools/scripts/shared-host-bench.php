#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Harness شبیه‌سازی هاست اشتراکی (DOC-134)
 *
 * برای هر پروفایل php.ini:
 *   ۱) بوت سرد (۳ بار، هر بار فرآیند تازه → opcache سرد) — بدترین حالت
 *   ۲) سرور پایدار (php -S) با opcache گرم: warmup + N درخواست → میانگین/p50/p95
 *   ۳) گزارش حافظه، footprint و وضعیت opcache
 *
 * استفاده:
 *   php tools/scripts/shared-host-bench.php [profile,...] [--requests N] [--scenario s]
 * پروفایل‌ها: minimum|ideal|heavy|restricted|noopt (پیش‌فرض: همه)
 */

$base = \dirname(__DIR__, 2);
$profiles = ['minimum', 'ideal', 'heavy', 'restricted', 'noopt'];
$requests = 300;
$scenarios = ['time', 'view', 'db'];
$port = 8199;

$args = \array_slice($argv, 1);
for ($i = 0; $i < \count($args); $i++) {
    if ($args[$i] === '--requests') { $requests = (int) $args[++$i]; }
    elseif ($args[$i] === '--port') { $port = (int) $args[++$i]; }
    elseif ($args[$i] === '--scenario') { $scenarios = [\explode(',', $args[++$i])][0]; }
    elseif ($args[$i] === '--profiles') { $profiles = \explode(',', $args[++$i]); }
}

$iniFor = static function (string $p) use ($base): ?string {
    $f = $base . "/examples/hosting/php-{$p}.ini";
    return \is_file($f) ? $f : null;   // null = بدون ini (پیش‌فرض، برای noopt)
};

// noopt = پروفایل پیش‌فرض CLI با opcache خاموش (بدترین حالت مطلق)
$extraFor = static fn (string $p): array => $p === 'noopt' ? ['-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0'] : [];

$mkLog = static fn (string $p): string => $base . "/storage/temp/bench-{$p}.log";

$run = static function (array $cmd, array $env = []) use ($base): array {
    $envFull = \array_merge(\getenv(), $env, ['XDEBUG_MODE' => 'off']);
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = \proc_open($cmd, $desc, $pipes, $base, $envFull);
    if (!\is_resource($proc)) { return ['', 'proc_open failed', -1]; }
    $out = \stream_get_contents($pipes[1]); \fclose($pipes[1]);
    $err = \stream_get_contents($pipes[2]); \fclose($pipes[2]);
    $code = \proc_close($proc);
    return [$out, $err, $code];
};

$percentile = static function (array $sorted, float $p): float {
    if ($sorted === []) { return 0.0; }
    $idx = (int) \ceil($p / 100 * \count($sorted)) - 1;
    return $sorted[\max(0, \min($idx, \count($sorted) - 1))];
};

$results = [];

foreach ($profiles as $profile) {
    $ini = $iniFor($profile);
    $extra = $extraFor($profile);
    $log = $mkLog($profile);
    @\unlink($log);
    \fwrite(STDERR, "\n═══ پروفایل: {$profile} " . ($ini ? "($ini)" : '(default CLI)') . " ═══\n");

    // ── ۱) بوت سرد (فرآیند تازه برای هر سناریو) ─────────────────────────────
    $cold = [];
    foreach ($scenarios as $sc) {
        $cmd = \array_merge([PHP_BINARY], $extra, $ini ? ['-c', $ini] : [],
            ['-d', 'opcache.enable_cli=1', $base . '/tools/scripts/shared-host-measure.php', $sc]);
        [$out] = $run($cmd);
        $line = \trim((string) \preg_replace('/^.*?(\{.*\}).*$/s', '$1', $out));
        $j = \json_decode($line, true);
        if (\is_array($j)) { $cold[$sc] = $j; }
    }

    // ── ۲) سرور پایدار (opcache گرم) ────────────────────────────────────────
    $srvCmd = \array_merge([PHP_BINARY], $extra, $ini ? ['-c', $ini] : [],
        ['-S', "127.0.0.1:{$port}", $base . '/tools/scripts/bench-server.php']);
    $srvProc = \proc_open($srvCmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $sp, $base,
        \array_merge(\getenv(), ['XDEBUG_MODE' => 'off', 'TVN_BENCH_LOG' => $log]));
    if (!\is_resource($srvProc)) { \fwrite(STDERR, "  ✗ سرور بالا نیامد\n"); continue; }

    // انتظار برای آماده‌شدن سرور
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $s = @\fsockopen('127.0.0.1', $port, $en, $es, 0.2);
        if ($s) { \fclose($s); $ready = true; break; }
        \usleep(100000);
    }

    $warm = [];
    if ($ready) {
        foreach ($scenarios as $sc) {
            $url = "http://127.0.0.1:{$port}/__measure/{$sc}";
            // warmup: ۵ درخواست تا opcache و classmap داغ شوند
            for ($i = 0; $i < 5; $i++) { @\file_get_contents($url); }
            // اندازه‌گیری
            $kernels = []; $peaks = []; $last = null;
            for ($i = 0; $i < $requests; $i++) {
                $ctx = \stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 30]]);
                $body = @\file_get_contents($url, false, $ctx);
                $j = $body ? \json_decode($body, true) : null;
                if (\is_array($j)) { $kernels[] = $j['kernel_ms']; $peaks[] = $j['peak_alloc_mb']; $last = $j; }
            }
            \sort($kernels); \sort($peaks);
            $warm[$sc] = [
                'n'          => \count($kernels),
                'status'     => $last['status'] ?? null,
                'kernel_avg' => \round(\array_sum($kernels) / \max(1, \count($kernels)), 2),
                'kernel_p50' => \round($percentile($kernels, 50), 2),
                'kernel_p95' => \round($percentile($kernels, 95), 2),
                'kernel_max' => \round(\end($kernels) ?: 0, 2),
                'peak_alloc_mb_p95' => \round($percentile($peaks, 95), 2),
                'opcache_used_mb'   => $last['opcache']['used_mb'] ?? null,
                'opcache_scripts'   => $last['opcache']['scripts'] ?? null,
                'opcache_hits'      => $last['opcache']['hits'] ?? null,
                'opcache_misses'    => $last['opcache']['misses'] ?? null,
                'files'             => $last['files'] ?? null,
                'classes'           => $last['classes'] ?? null,
            ];
        }
    }

    // پاک‌کردن سرور (بدون pkill: از روی /proc)
    if (\is_resource($srvProc)) { \proc_terminate($srvProc); \proc_close($srvProc); }
    // اطمینان از مرگ فرزندان php -S
    foreach (\glob('/proc/[0-9]*/cmdline') ?: [] as $cf) {
        $cl = @\file_get_contents($cf);
        if ($cl !== false && \str_contains($cl, "127.0.0.1:{$port}") && \str_contains($cl, 'bench-server.php')) {
            $pid = (int) \explode('/', $cf)[2];
            @\posix_kill($pid, 9);
        }
    }
    \usleep(200000);

    $results[$profile] = [
        'ini'      => $ini ? \basename($ini) : '(default)',
        'cold'     => $cold,
        'warm'     => $warm,
    ];
    \fwrite(STDERR, "  ✓ سرد: " . \count($cold) . " سناریو | گرم: " . \count($warm) . " سناریو × {$requests}\n");
}

$outFile = $base . '/storage/temp/shared-host-results.json';
\file_put_contents($outFile, \json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo $outFile, PHP_EOL;
\fwrite(STDERR, "\nنتایج → {$outFile}\n");

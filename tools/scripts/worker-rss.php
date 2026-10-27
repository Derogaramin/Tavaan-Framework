#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — سنجهٔ RSS ورکر گرم (DOC-134 §5.6)
 *
 * سرور پایدار (php -S ≈ یک ورکر php-fpm/LiteSpeed) را بالا می‌آورد، گرم می‌کند،
 * سپس VmRSS واقعی فرآیند را از /proc می‌خواند. این عدد کلید محاسبهٔ
 * pm.max_children در هاست اشتراکی است.
 *
 * استفاده: php tools/scripts/worker-rss.php [profile,...] [--port N] [--hits N]
 */

$base = \dirname(__DIR__, 2);
$profiles = ['minimum', 'ideal', 'heavy', 'restricted', 'noopt'];
$port = 8231;
$hits = 40;
$args = \array_slice($argv, 1);
for ($i = 0; $i < \count($args); $i++) {
    if ($args[$i] === '--port') { $port = (int) $args[++$i]; }
    elseif ($args[$i] === '--hits') { $hits = (int) $args[++$i]; }
    elseif ($args[$i] === '--profiles') { $profiles = \explode(',', $args[++$i]); }
}

$rssKb = static function (int $pid): ?int {
    $st = @\file_get_contents("/proc/{$pid}/status");
    if ($st === false) { return null; }
    if (\preg_match('/VmRSS:\s+(\d+)\s+kB/', $st, $m)) { return (int) $m[1]; }
    return null;
};

$out = [];
foreach ($profiles as $profile) {
    $ini = $base . "/examples/hosting/php-{$profile}.ini";
    $extra = $profile === 'noopt' ? ['-d', 'opcache.enable=0'] : [];
    $cmd = \array_merge([PHP_BINARY], $extra, \is_file($ini) ? ['-c', $ini] : [],
        ['-S', "127.0.0.1:{$port}", $base . '/tools/scripts/bench-server.php']);
    $log = $base . "/storage/temp/rss-{$profile}.log";
    @\unlink($log);

    $proc = \proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $base,
        \array_merge(\getenv(), ['XDEBUG_MODE' => 'off', 'TVN_BENCH_LOG' => $log]));
    if (!\is_resource($proc)) { continue; }
    $pid = \proc_get_status($proc)['pid'];

    for ($i = 0; $i < 50; $i++) { $s = @\fsockopen('127.0.0.1', $port, $e, $es, 0.2); if ($s) { \fclose($s); break; } \usleep(80000); }

    // پیدا کردن pid واقعی php (proc_open پوسته می‌سازد)
    $realPid = $pid;
    foreach (\glob('/proc/[0-9]*/cmdline') ?: [] as $cf) {
        $cl = @\file_get_contents($cf);
        if ($cl !== false && \str_contains($cl, "127.0.0.1:{$port}") && \str_contains($cl, 'bench-server.php')) {
            $realPid = (int) \explode('/', $cf)[2];
            break;
        }
    }

    // سناریوهای گرم
    $scenRss = [];
    foreach (['time', 'view', 'db'] as $sc) {
        $url = "http://127.0.0.1:{$port}/__measure/{$sc}";
        for ($i = 0; $i < $hits; $i++) { @\file_get_contents($url); }
        $r = $rssKb($realPid);
        $scenRss[$sc] = $r !== null ? \round($r / 1024, 1) : null;   // MB
    }

    \proc_terminate($proc); \proc_close($proc);
    foreach (\glob('/proc/[0-9]*/cmdline') ?: [] as $cf) {
        $cl = @\file_get_contents($cf);
        if ($cl !== false && \str_contains($cl, "127.0.0.1:{$port}")) {
            @\posix_kill((int) \explode('/', $cf)[2], 9);
        }
    }
    \usleep(150000);

    $out[$profile] = $scenRss;
    \fwrite(STDERR, \sprintf("%-11s RSS(MB): time=%s view=%s db=%s\n", $profile,
        $scenRss['time'] ?? '?', $scenRss['view'] ?? '?', $scenRss['db'] ?? '?'));
}

\file_put_contents($base . '/storage/temp/worker-rss.json', \json_encode($out, JSON_PRETTY_PRINT));
echo $base . '/storage/temp/worker-rss.json', PHP_EOL;

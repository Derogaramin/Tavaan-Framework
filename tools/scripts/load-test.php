<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — ابزار توسعه: تست بار (load test) با curl_multi.
 *
 * استفاده:
 *   php tools/scripts/load-test.php <url> <total-requests> <concurrency> [post-body-json]
 *
 * خروجی: RPS، latency (avg/p50/p95/max)، توزیع کدهای وضعیت، خطاها.
 * نکته: خودِ تولیدکنندهٔ بار هم CPU مصرف می‌کند — روی ماشین‌های کوچک
 * اعداد «کفِ بدبینانه» هستند.
 */

$url = $argv[1] ?? '';
$total = (int) ($argv[2] ?? 100);
$concurrency = (int) ($argv[3] ?? 10);
$postBody = $argv[4] ?? null;

if ($url === '' || $total < 1 || $concurrency < 1) {
    fwrite(STDERR, "usage: load-test.php <url> <total> <concurrency> [post-json]\n");
    exit(2);
}

$codes = [];
$latencies = [];
$errors = 0;
$bodies = 0;

$mh = curl_multi_init();
$active = [];
$launched = 0;
$done = 0;

$makeHandle = static function () use ($url, $postBody): CurlHandle {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/json', 'User-Agent: TavaanLoad/1.0'],
    ]);
    if ($postBody !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'User-Agent: TavaanLoad/1.0']);
    }
    return $ch;
};

// پر کردن اولیهٔ استخر
while ($launched < $total && count($active) < $concurrency) {
    $ch = $makeHandle();
    curl_multi_add_handle($mh, $ch);
    $active[(int) $ch] = ['h' => $ch, 't' => microtime(true)];
    $launched++;
}

$start = microtime(true);

do {
    $status = curl_multi_exec($mh, $running);

    while ($info = curl_multi_info_read($mh)) {
        $ch = $info['handle'];
        $key = (int) $ch;
        $elapsed = (microtime(true) - $active[$key]['t']) * 1000;
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($info['result'] !== CURLE_OK) {
            $errors++;
        } else {
            $codes[$code] = ($codes[$code] ?? 0) + 1;
            $latencies[] = $elapsed;
            $bodies += (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        }

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        unset($active[$key]);
        $done++;

        // جایگزینی تا سقف کل درخواست‌ها
        if ($launched < $total) {
            $nch = $makeHandle();
            curl_multi_add_handle($mh, $nch);
            $active[(int) $nch] = ['h' => $nch, 't' => microtime(true)];
            $launched++;
        }
    }

    if ($running > 0 && $status === CURLM_OK) {
        curl_multi_select($mh, 0.05);
    }
} while ($done < $total && ($running > 0 || $active !== []));

$wall = microtime(true) - $start;

sort($latencies);
$n = count($latencies);
$pct = static fn (float $p): float => $n === 0 ? 0.0 : $latencies[min($n - 1, (int) floor($p / 100 * $n))];

printf("URL: %s\n", $url);
printf("requests: %d | concurrency: %d | method: %s\n", $total, $concurrency, $postBody === null ? 'GET' : 'POST');
printf("wall: %.2fs → RPS: %.1f\n", $wall, $done / max($wall, 0.0001));
printf("latency ms: avg=%.1f p50=%.1f p95=%.1f max=%.1f\n", $n === 0 ? 0 : array_sum($latencies) / $n, $pct(50), $pct(95), $pct(99) >= 0 ? ($n === 0 ? 0 : $latencies[$n - 1]) : 0);
printf("status codes: %s\n", json_encode($codes));
printf("transport errors: %d\n", $errors);
printf("bytes downloaded: %d (avg %.0f B/resp)\n", $bodies, $done === 0 ? 0 : $bodies / $done);

<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — کاوشگر سقف حافظه (DOC-134 §5.5)
 *
 * زیرسیستم‌های پرمصرف را با بار فزاینده می‌آزماید تا «سقف واقعی حافظه» و
 * توجیه memory_limit در سه سطح (128M/256M/512M) به‌دست آید.
 * هر آزمون در فرآیند جدا (حافظه اوج دقیق، بدون آلودگی متقابل).
 *
 * استفاده: php tools/scripts/memory-ceiling.php <test> <size>
 * آزمون‌ها: report|json|view|validate|pipeline|serialize|http-big
 */

\define('TVN_BASE_PATH', \dirname(__DIR__, 2));
require TVN_BASE_PATH . '/core/bootstrap.php';

use Tavaan\Core\Application\ApplicationBuilder;

$test = $argv[1] ?? 'report';
$n = (int) ($argv[2] ?? 1000);
// به‌صورت پیش‌فرض، memory_limit پروفایل ini (-c) اعمال می‌شود تا «سقف واقعی»
// هر سطح دیده شود. با TVN_MEM_PROBE_UNLIMITED=1 سقف برداشته می‌شود (رشد خالص).
if (\getenv('TVN_MEM_PROBE_UNLIMITED') === '1') {
    \ini_set('memory_limit', '1024M');
}

$app = ApplicationBuilder::create(TVN_BASE_PATH)->boot();
$base = \memory_get_usage(true);

$peak = static fn (): float => \round((\memory_get_peak_usage(true) - $GLOBALS['base']) / 1048576, 2);

$t0 = \hrtime(true);
$result = null;
try {
    switch ($test) {
        case 'report':
            // موتور گزارش: dataset بزرگ + groupBy + pivot
            $rows = [];
            $st = ['paid', 'pending', 'canceled'];
            $ct = ['تهران', 'مشهد', 'شیراز', 'تبریز', 'اصفهان', 'اهواز', 'کرج'];
            for ($i = 0; $i < $n; $i++) {
                $rows[] = [
                    'created_at' => \sprintf('2026-%02d-%02d 10:00:00', 1 + ($i % 12), 1 + ($i % 28)),
                    'total' => 450_000 + $i * 837,
                    'status' => $st[$i % 3],
                    'city' => $ct[$i % 7],
                    'qty' => 1 + ($i % 9),
                ];
            }
            $report = \Tavaan\Core\Facade\Facades\Report::build('bench')
                ->dataset($rows)
                ->addField('created_at', label: 'تاریخ', type: 'date', format: 'jalali')
                ->addField('total', label: 'مبلغ', type: 'money', aggregate: 'sum')
                ->addField('status', label: 'وضعیت')
                ->addField('city', label: 'شهر')
                ->groupBy('status', subtotal: ['total' => 'sum'])
                ->pivot(rows: 'status', columns: 'city', values: 'total', aggregate: 'sum');
            $result = \Tavaan\Core\Facade\Facades\Report::render($report, []);
            break;

        case 'json':
            $big = [];
            for ($i = 0; $i < $n; $i++) {
                $big[] = ['id' => $i, 'name' => 'رکورد-' . $i, 'tags' => \range(1, 10), 'meta' => ['a' => $i, 'b' => \str_repeat('x', 50)]];
            }
            $result = \strlen((string) \json_encode($big, JSON_UNESCAPED_UNICODE));
            break;

        case 'view':
            // رندر قالب با حلقهٔ بزرگ (ViewEngine)
            $items = [];
            for ($i = 0; $i < $n; $i++) { $items[] = ['id' => $i, 'title' => 'آیتم ' . $i]; }
            $tpl = TVN_BASE_PATH . '/storage/temp/_mem_view.php';
            \file_put_contents($tpl, '<ul>@foreach($items as $it)<li>{{ $it[\'id\'] }} — {{ $it[\'title\'] }}</li>@endforeach</ul>');
            $vf = $app->make(\Tavaan\Engine\View\ViewFactory::class);
            $result = \strlen($vf->render(TVN_BASE_PATH . '/storage/temp/_mem_view.php', ['items' => $items]));
            break;

        case 'validate':
            $payload = [];
            for ($i = 0; $i < $n; $i++) { $payload['f' . $i] = 'value-' . $i; }
            $rules = [];
            for ($i = 0; $i < $n; $i++) { $rules['f' . $i] = 'required|string|max:255'; }
            $v = $app->make(\Tavaan\Engine\Validation\Validator::class, ['data' => $payload, 'rules' => $rules]);
            $result = $v->passes() ? 'ok' : 'fail';
            break;

        case 'pipeline':
            $data = \range(1, $n);
            $pipe = $app->make(\Tavaan\Engine\Pipeline\Pipeline::class);
            $result = $pipe->send($data)
                ->through([
                    static fn ($d, $next) => $next(\array_map(static fn ($x) => $x * 2, $d)),
                    static fn ($d, $next) => $next(\array_filter($d, static fn ($x) => $x % 3 !== 0)),
                    static fn ($d, $next) => $next(\array_values($d)),
                ])
                ->then(static fn ($d) => \count($d));
            break;

        case 'serialize':
            $obj = [];
            for ($i = 0; $i < $n; $i++) { $obj[] = ['k' => $i, 'v' => \str_repeat('توان', 20)]; }
            $s = \serialize($obj);
            $u = \unserialize($s);
            $result = \strlen($s) + (\is_array($u) ? \count($u) : 0);
            break;

        default:
            $result = 'unknown-test';
    }
} catch (\Throwable $e) {
    echo \json_encode(['test' => $test, 'n' => $n, 'error' => $e::class . ': ' . $e->getMessage(), 'peak_mb_at_error' => $peak()]), PHP_EOL;
    exit(1);
}
$ms = (\hrtime(true) - $t0) / 1e6;

echo \json_encode([
    'test' => $test, 'n' => $n,
    'peak_mb' => $peak(),
    'ms' => \round($ms, 1),
    'result_kind' => \is_scalar($result) ? (string) $result : \get_debug_type($result),
    'result_len' => \is_string($result) ? \strlen($result) : (\is_array($result) ? \count($result) : null),
], JSON_UNESCAPED_UNICODE), PHP_EOL;

<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — روت‌های وب
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق).
 */

use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

Route::view('/', 'welcome')->name('home');
Route::get('/hello/{name}', static function (string $name): string {
    return view('hello', ['name' => $name])->render();
})->name('hello')->where('name', '[\\p{L}\\p{N}\\-_]+');   // slug یونیکد (فارسی مجاز)

Route::get('/time', static function (): Response {
    return Response::json([
        'jalali' => jalali(null, 'Y/m/d H:i:s l'),
        'gregorian' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
    ]);
})->name('time');

Route::prefix('demo')->name('demo.')->group(static function (): void {
    Route::get('/counter', static function (Request $request): Response {
        $session = $request->session();
        $count = $session !== null ? $session->increment('visits') : -1;

        return Response::json(['visits' => $count, 'session_id' => $session?->id()]);
    })->name('counter');
});

Route::get('/xss-test', static fn (): string => view('xss-test', ['input' => '<script>alert(1)</script>'])->render());

// ── دموهای M7 (دامنه) — خودکفا و بدون نیاز به شبکهٔ خارجی ─────────────────

Route::prefix('demo')->name('demo.')->group(static function (): void {
    // 📊 گزارش زنده — Report engine (DOC-072)
    Route::get('/report', static function (Request $request): mixed {
        $rows = [];
        $statuses = ['paid', 'pending', 'canceled'];
        $cities = ['تهران', 'مشهد', 'شیراز', 'تبریز', 'اصفهان'];
        for ($i = 0; $i < 60; $i++) {
            $rows[] = [
                'created_at' => \sprintf('2026-10-%02d 10:00:00', 1 + ($i % 17)),
                'total' => 450_000 + $i * 83_000,
                'status' => $statuses[$i % 3],
                'city' => $cities[$i % 5],
            ];
        }

        $report = \Tavaan\Core\Facade\Facades\Report::build('فروش-نمونه')
            ->dataset($rows)
            ->addField('created_at', label: 'تاریخ', type: 'date', format: 'jalali')
            ->addField('total', label: 'مبلغ (تومان)', type: 'money', aggregate: 'sum')
            ->addField('status', label: 'وضعیت')
            ->addField('city', label: 'شهر')
            ->addFilter('status', field: 'status', type: 'select', options: ['paid', 'pending', 'canceled'])
            ->groupBy('status', subtotal: ['total' => 'sum'])
            ->pivot(rows: 'status', columns: 'city', values: 'total', aggregate: 'sum')
            ->orderBy('total', 'desc');

        $filters = [];
        $status = $request->query('status');
        if (\is_string($status) && $status !== '') {
            $filters['status'] = $status;
        }

        return \Tavaan\Core\Facade\Facades\Report::render($report, $filters);
    })->name('report');

    // 📥 خروجی CSV همان گزارش (BOM + delimiter «;» — آمادهٔ Excel فارسی)
    Route::get('/report/csv', static function (): Response {
        $rows = [];
        for ($i = 0; $i < 60; $i++) {
            $rows[] = [
                'created_at' => \sprintf('2026-10-%02d 10:00:00', 1 + ($i % 17)),
                'total' => 450_000 + $i * 83_000,
                'status' => ['paid', 'pending', 'canceled'][$i % 3],
                'city' => ['تهران', 'مشهد', 'شیراز', 'تبریز', 'اصفهان'][$i % 5],
            ];
        }
        $report = \Tavaan\Core\Facade\Facades\Report::build('فروش-نمونه')
            ->dataset($rows)
            ->addField('created_at', label: 'تاریخ', type: 'date', format: 'jalali')
            ->addField('total', label: 'مبلغ (تومان)', type: 'money', aggregate: 'sum')
            ->addField('status', label: 'وضعیت')
            ->addField('city', label: 'شهر');

        $csv = \Tavaan\Core\Facade\Facades\Report::export($report, 'csv');

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tavaan-sales-demo.csv"',
        ]);
    })->name('report.csv');

    // 🔁 گردش‌کار زنده — Workflow engine (DOC-071، درایور array بدون DB)
    Route::get('/workflow', static function (): string {
        $events = app()->bound('events') ? app()->make('events') : null;
        $manager = new \Tavaan\Engine\Workflow\WorkflowManager(
            new \Tavaan\Engine\Workflow\Store\ArrayStore(),
            [],
            $events instanceof \Tavaan\Core\Contracts\Events\DispatcherInterface ? $events : null,
            app(),
        );

        $manager->define('order-approval', function (\Tavaan\Engine\Workflow\ProcessBuilder $p): void {
            $p->start('draft');
            $p->state('pending_review', \Tavaan\Engine\Workflow\StateType::UserTask)->assignee('user:7');
            $p->state('approved');
            $p->state('completed', \Tavaan\Engine\Workflow\StateType::End);
            $p->exclusiveGateway('check_amount');
            $p->transition('submit', from: 'draft', to: 'pending_review');
            $p->transition('review', from: 'pending_review', to: 'check_amount');
            $p->transition('approve', from: 'check_amount', to: 'approved')
                ->guard(static fn (\Tavaan\Engine\Workflow\ProcessContext $c): bool => (int) $c->get('amount', 0) < 10_000_000);
            $p->transition('complete', from: 'approved', to: 'completed');
        });

        $steps = [];
        $instance = $manager->start('order-approval', 'order', 'DEMO-1', ['amount' => 4_500_000]);
        $steps[] = ['start', $instance->currentState()];
        $instance->fire('submit');
        $steps[] = ['fire(submit)', $instance->currentState()];

        $task = $manager->tasksFor(7)[0] ?? null;
        if ($task !== null) {
            $task->claim(7);
            $steps[] = ['task.claim(7)', 'claimed'];
            $instance = $task->complete([], 'review');
            $steps[] = ['task.complete(review)', $instance->currentState()];
        }
        $instance->fire('complete');
        $steps[] = ['fire(complete)', $instance->currentState() . ' ✅'];

        $mermaid = (new \Tavaan\Engine\Workflow\Visualizer())->toMermaid($manager->definition('order-approval'));

        return view('demo/workflow', [
            'steps' => $steps,
            'history' => $instance->history(),
            'completed' => $instance->isCompleted(),
            'mermaid' => $mermaid,
        ])->render();
    })->name('workflow');

    // 💳 پرداخت کیف پول داخلی — Payment engine (DOC-073، بدون شبکه)
    Route::get('/payment', static function (): string {
        $steps = [];
        $error = null;
        $balances = ['before' => null, 'after_pay' => null, 'after_refund' => null];
        $transactionId = null;

        try {
            if (!app()->bound('schema') || !app()->make('schema')->hasTable('tvn_payment_transactions')) {
                $error = 'جدول تراکنش‌ها موجود نیست — ابتدا `php bin/tvn migrate` را اجرا کنید.';
            } else {
                app()->make('config')->set('payment.default', 'wallet');
                app()->make('config')->set('payment.gateways.wallet.accounts', ['demo-user' => 2_500_000]);

                /** @var \Tavaan\Engine\Payment\PaymentManager $payments */
                $payments = app()->make('payment');
                $wallet = $payments->gateway('wallet');
                $balances['before'] = $wallet->balanceOf('demo-user');

                $purchase = $payments->purchase(new \Tavaan\Engine\Payment\PurchaseRequest(
                    amount: \Tavaan\Engine\Database\ORM\Support\Money::of(750_000, 'IRT'),
                    callbackUrl: route('payment.result'),
                    orderId: 'DEMO-' . \random_int(1000, 9999),
                    meta: ['user_id' => 'demo-user', 'order_type' => 'demo'],
                ), 'wallet');
                $steps[] = ['purchase', $purchase->success ? '✅ authority=' . \substr($purchase->authority, 0, 18) . '…' : '❌ ' . $purchase->errorCode];

                $verify = $payments->verify($purchase->authority);
                $steps[] = ['verify', 'وضعیت: ' . $verify->status->value . ' (🔒 با مبلغ محلی تراکنش)'];
                $balances['after_pay'] = $wallet->balanceOf('demo-user');

                $tx = $payments->findByAuthority($purchase->authority);
                $transactionId = $tx === null ? null : (string) $tx->getKey();

                $refund = $tx === null ? null : $payments->refund($tx);
                $steps[] = ['refund', $refund !== null && $refund->success ? '✅ بازگشت وجه' : '❌'];
                $balances['after_refund'] = $wallet->balanceOf('demo-user');
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('demo/payment', [
            'steps' => $steps,
            'balances' => $balances,
            'error' => $error,
            'transactionId' => $transactionId,
        ])->render();
    })->name('payment');
});

// صفحهٔ نتیجهٔ پرداخت — مقصد redirect پس از callback (config: payment.result_url)
Route::get('/payment/result', static function (Request $request): string {
    $id = (string) $request->query('transaction', '');
    $status = (string) $request->query('status', '');
    $tx = null;
    $error = null;

    try {
        if ($id !== '' && app()->bound('schema') && app()->make('schema')->hasTable('tvn_payment_transactions')) {
            $tx = app()->make('payment')->findTransaction($id);
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

    return view('payment/result', ['tx' => $tx, 'id' => $id, 'status' => $status, 'error' => $error])->render();
})->name('payment.result');

Route::fallback(static fn (): Response => new Response(
    (string) view('errors/404', ['path' => $_SERVER['REQUEST_URI'] ?? ''])->render(),
    404,
));

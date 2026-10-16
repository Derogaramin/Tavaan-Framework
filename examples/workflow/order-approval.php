<?php

/**
 * تَوان | Tavaan — نمونهٔ گردش‌کار: تأیید سفارش (M7 / DOC-071)
 *
 * اجرا: php examples/workflow/order-approval.php
 *
 * نمایش: DSL تعریف فرایند → start → fire با guard → دروازهٔ انحصاری
 * → Human Task (claim/complete) → Timer → History.
 * (بدون DB — درایور array)
 */

declare(strict_types=1);

use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Engine\Workflow\ProcessBuilder;
use Tavaan\Engine\Workflow\ProcessContext;
use Tavaan\Engine\Workflow\StateType;

require \dirname(__DIR__, 2) . '/core/bootstrap.php';

$app = ApplicationBuilder::create(TVN_BASE_PATH)->forConsole()->boot();
$app->make('config')->set('workflow.driver', 'array');

/** @var \Tavaan\Engine\Workflow\WorkflowManager $workflows */
$workflows = $app->make('workflow');

// ── ۱) تعریف فرایند با DSL ────────────────────────────────────────────────
$workflows->define('order-approval', function (ProcessBuilder $p): void {
    $p->start('draft');

    $p->state('pending_review', type: StateType::UserTask)
        ->assignee('user:7')
        ->form('admin/orders/review');

    $p->state('approved');
    $p->state('rejected', type: StateType::End);
    $p->state('completed', type: StateType::End);
    $p->exclusiveGateway('check_amount');

    $p->transition('submit', from: 'draft', to: 'pending_review')
        ->action(fn (ProcessContext $c) => print('  📣 اطلاع به بررسی‌کننده (سفارش ' . $c->get('order_id') . ')' . PHP_EOL));

    $p->transition('review', from: 'pending_review', to: 'check_amount');
    $p->transition('approve', from: 'check_amount', to: 'approved')
        ->guard(fn (ProcessContext $c): bool => (int) $c->get('amount', 0) < 10_000_000);
    $p->transition('escalate', from: 'check_amount', to: 'rejected')   // در این نمونه: مبالغ بالا مستقیم رد
        ->guard(fn (ProcessContext $c): bool => (int) $c->get('amount', 0) >= 10_000_000);
    $p->transition('reject', from: 'pending_review', to: 'rejected');
    $p->transition('complete', from: 'approved', to: 'completed');

    $p->timerEvent('auto_reject', after: '3 days', on: 'pending_review', to: 'rejected');
});

// ── ۲) شروع فرایند ────────────────────────────────────────────────────────
$instance = $workflows->start('order-approval', 'order', '1001', [
    'amount' => 4_500_000,
    'order_id' => 'ORD-1001',
]);

echo 'وضعیت فعلی: ' . $instance->currentState() . PHP_EOL;

$instance->fire('submit');
echo 'پس از submit: ' . $instance->currentState() . PHP_EOL;

// ── ۳) کار انسانی: claim + complete ──────────────────────────────────────
$tasks = $workflows->tasksFor(7);
$task = $tasks[0];
$task->claim(7);
echo 'کار توسط کاربر ۷ تصاحب شد.' . PHP_EOL;

$instance = $task->complete(['decision' => 'approved'], 'review');   // instance تازه (پس از گذار)
echo 'پس از بررسی (دروازهٔ انحصاری، مبلغ < ۱۰M): ' . $instance->currentState() . PHP_EOL;

$instance->fire('complete');
echo 'پایان: ' . $instance->currentState() . ' — تکمیل‌شده؟ ' . ($instance->isCompleted() ? 'بله ✅' : 'خیر') . PHP_EOL;

// ── ۴) تاریخچه ────────────────────────────────────────────────────────────
echo PHP_EOL . 'تاریخچهٔ گذارها:' . PHP_EOL;
foreach ($instance->history() as $row) {
    echo \sprintf("  %s → %s (%s)", $row['from_state'], $row['to_state'], $row['transition']) . PHP_EOL;
}

// ── ۵) نمودار DOT (Visualizer — AC-WF-11) ────────────────────────────────
echo PHP_EOL . 'نمودار فرایند (Graphviz DOT — قابل رندر با dot -Tsvg):' . PHP_EOL;
echo (new \Tavaan\Engine\Workflow\Visualizer())->toDot($workflows->definition('order-approval'));

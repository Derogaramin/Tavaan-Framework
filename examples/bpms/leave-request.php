<?php

/**
 * تَوان | Tavaan — نمونهٔ BPMS: هستهٔ دامنهٔ فرایند (M9 / DOC-098)
 *
 * اجرا: php examples/bpms/leave-request.php
 *
 * نمایش (بدون DB — دامنهٔ خالص):
 * ۱) تعریف فرایند JSON (DOC-098 §3) → ProcessModel + اعتبارسنجی ModelValidator
 * ۲) ExpressionEvaluator امن (بدون eval) — شرط‌های gateway + رد تزریق
 * ۳) فرم‌ساز پویا (§4) — schema → قواعد validation
 * ۴) کامپایل به قالب موتور workflow (mirror) + نمودار Mermaid/SVG
 *
 * برای اجرای کامل موتور (start/completeTask/timer) روی DB:
 * `php bin/tvn kit:install bpms --with-demo` و `tools/scripts/smoke-bpms.php`.
 */

declare(strict_types=1);

use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\Services\DiagramGenerator;
use Modules\Bpms\Domain\Services\ExpressionEvaluator;
use Modules\Bpms\Domain\Services\ModelValidator;
use Modules\Bpms\Domain\Services\WorkflowDefinitionCompiler;
use Modules\Bpms\Domain\ValueObjects\FormSchema;
use Modules\Bpms\Domain\ValueObjects\ProcessModel;

require \dirname(__DIR__, 2) . '/core/bootstrap.php';

// ── ۱) تعریف فرایند «درخواست مرخصی» — دقیقاً DOC-098 §3 ──────────────────
$definition = [
    'startEvent' => 'start',
    'elements' => [
        ['id' => 'start', 'type' => 'startEvent', 'next' => 'submit'],
        ['id' => 'submit', 'type' => 'userTask', 'name' => 'ثبت درخواست',
         'assignee' => 'initiator', 'form' => 'leave_form', 'next' => 'check_days'],
        ['id' => 'check_days', 'type' => 'exclusiveGateway', 'name' => 'بررسی تعداد روز',
         'conditions' => [
             ['expr' => 'variables.days <= 2', 'next' => 'auto_approve'],
             ['default' => true, 'next' => 'manager_approval'],
         ]],
        ['id' => 'auto_approve', 'type' => 'serviceTask', 'name' => 'تأیید خودکار',
         'action' => 'mark_done', 'next' => 'notify'],
        ['id' => 'manager_approval', 'type' => 'userTask', 'name' => 'تأیید مدیر',
         'assignee' => 'role:manager', 'due' => 'P2D', 'next' => 'decision'],
        ['id' => 'decision', 'type' => 'exclusiveGateway',
         'conditions' => [
             ['expr' => 'variables.approved', 'next' => 'notify'],
             ['default' => true, 'next' => 'reject'],
         ]],
        ['id' => 'notify', 'type' => 'serviceTask', 'action' => 'notify_initiator', 'next' => 'end'],
        ['id' => 'reject', 'type' => 'serviceTask', 'action' => 'notify_initiator', 'next' => 'end'],
        ['id' => 'end', 'type' => 'endEvent'],
    ],
    'timers' => [
        ['on' => 'manager_approval', 'after' => 'P2D', 'action' => 'escalate'],
    ],
];

$forms = [
    'leave_form' => [
        'id' => 'leave_form',
        'fields' => [
            ['name' => 'leave_type', 'type' => 'select', 'label' => 'نوع مرخصی',
             'options' => ['استحقاقی', 'استعلاجی', 'بدون حقوق'], 'required' => true],
            ['name' => 'days', 'type' => 'number', 'label' => 'تعداد روز', 'min' => 1, 'max' => 30, 'required' => true],
            ['name' => 'from_date', 'type' => 'jalali_date', 'label' => 'از تاریخ', 'required' => true],
            ['name' => 'reason', 'type' => 'textarea', 'label' => 'دلیل'],
        ],
    ],
];

$model = ProcessModel::fromArray($definition);
$problems = (new ModelValidator())->validate($definition, $forms);

echo "۱) تعریف فرایند «درخواست مرخصی»\n";
echo '   عناصر: ' . \count($model->elements()) . ' • تایمرها: ' . \count($model->timers) . "\n";
echo '   اعتبارسنجی: ' . ($problems === [] ? '✅ معتبر (قابل publish/اجرا)' : '❌ ' . \implode(' | ', $problems)) . "\n\n";

// ── ۲) ExpressionEvaluator امن — شرط‌های gateway ─────────────────────────
$evaluator = new ExpressionEvaluator();

echo "۲) ارزیاب عبارت امن (بدون eval — AC-BPMS-12)\n";
foreach ([
    ['variables.days <= 2', ['variables' => ['days' => 1]]],
    ['variables.days <= 2', ['variables' => ['days' => 5]]],
    ['variables.approved', ['variables' => ['approved' => true]]],
    ['variables.approved', ['variables' => ['approved' => false]]],
] as [$expr, $context]) {
    $result = $evaluator->evaluate($expr, $context) ? 'true' : 'false';
    echo "   {$expr} → {$result}\n";
}

// تزریق → رد قطعی
foreach (['system("rm -rf /")', '$_GET["x"]', 'variables.a; phpinfo()'] as $evil) {
    try {
        $evaluator->evaluate($evil, ['variables' => []]);
        echo "   ❌ پذیرفته شد (نباید!): {$evil}\n";
    } catch (BpmsException) {
        echo "   🔒 رد شد: {$evil}\n";
    }
}
echo "\n";

// ── ۳) فرم‌ساز پویا — schema → قواعد validation ──────────────────────────
$schema = FormSchema::fromArray($forms['leave_form']);

echo "۳) فرم‌ساز پویا (leave_form — {$schema->id})\n";
foreach ($schema->toValidationRules() as $field => $rules) {
    echo '   ' . $field . ' → ' . \implode('|', $rules) . "\n";
}

$normalized = $schema->normalize(['days' => '3', 'leave_type' => 'استحقاقی', 'from_date' => '1405/08/10', 'evil_key' => 'x']);
echo '   normalize: days=' . \var_export($normalized['days'], true) . ' (رشته→عدد) • evil_key حذف شد: '
    . (\array_key_exists('evil_key', $normalized) ? '❌' : '✅') . "\n\n";

// ── ۴) mirror موتور workflow + نمودار ────────────────────────────────────
$compiled = WorkflowDefinitionCompiler::compile('bpms-leave-request', $model);

echo "۴) کامپایل برای mirror موتور workflow\n";
echo '   states: ' . \count($compiled['states']) . ' • transitions: ' . \count($compiled['transitions']) . "\n";

$diagram = new DiagramGenerator();
echo "   Mermaid (۵ خط اول):\n";
foreach (\array_slice(\explode("\n", $diagram->toMermaid($model)), 0, 5) as $line) {
    echo '     ' . $line . "\n";
}
echo '   SVG: ' . \strlen($diagram->toSvg($model, ['manager_approval'])) . ' بایت (ready to embed)' . "\n\n";

echo "✅ پایان نمونه — نصب کامل کیت: php bin/tvn kit:install bpms --with-demo\n";

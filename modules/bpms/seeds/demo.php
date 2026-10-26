<?php

declare(strict_types=1);

/*
 * ماژول BPMS — seed دمو (DOC-098؛ kit:install bpms --with-demo).
 *
 * دادهٔ نمایشی:
 * - تعریف `leave-request` (دقیقاً سناریوی §3 + فرم §4) — منتشر/فعال
 * - تعریف `purchase-approval` (parallel gateway — fork/join) — منتشر/فعال
 * - نمونه‌ها: مرخصی ۱ روزه (auto-approve → completed)، مرخصی ۵ روزه
 *   (در انتظار تأیید مدیر + تایمر P2D)، خرید (دو کار موازی مالی/انبار)
 *
 * idempotent — با کلید موجود، رد می‌شود.
 * نکته: اکشن‌های serviceTask از داخلی‌های ثبت‌شدهٔ provider هستند
 * (mark_done/notify_initiator) — اکشن دامنه‌ای خاص (مثل approveLeave §6)
 * توسط اپلیکیشن ثبت می‌شود.
 */

use Modules\Bpms\Application\UseCases\DesignerService;
use Modules\Bpms\Application\UseCases\ProcessEngine;
use Tavaan\Core\Facade\Facades\DB;

return static function (): void {
    // ── تعریف ۱: درخواست مرخصی (DOC-098 §3/§4) ───────────────────────────
    $leaveDefinition = [
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
             'assignee' => 'role:manager', 'form' => 'approval_form', 'due' => 'P2D',
             'priority' => 'high', 'next' => 'decision'],
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

    $leaveForms = [
        'leave_form' => [
            'id' => 'leave_form',
            'title' => 'فرم مرخصی',
            'fields' => [
                ['name' => 'leave_type', 'type' => 'select', 'label' => 'نوع مرخصی',
                 'options' => ['استحقاقی', 'استعلاجی', 'بدون حقوق'], 'required' => true],
                ['name' => 'days', 'type' => 'number', 'label' => 'تعداد روز',
                 'min' => 1, 'max' => 30, 'required' => true],
                ['name' => 'from_date', 'type' => 'jalali_date', 'label' => 'از تاریخ', 'required' => true],
                ['name' => 'to_date', 'type' => 'jalali_date', 'label' => 'تا تاریخ', 'required' => true],
                ['name' => 'reason', 'type' => 'textarea', 'label' => 'دلیل'],
            ],
        ],
        'approval_form' => [
            'id' => 'approval_form',
            'title' => 'فرم تأیید مدیر',
            'fields' => [
                ['name' => 'approved', 'type' => 'checkbox', 'label' => 'تأیید می‌شود'],
                ['name' => 'manager_note', 'type' => 'textarea', 'label' => 'یادداشت مدیر'],
            ],
        ],
    ];

    // ── تعریف ۲: تأیید خرید با بررسی موازی (parallel gateway) ─────────────
    $purchaseDefinition = [
        'startEvent' => 'start',
        'elements' => [
            ['id' => 'start', 'type' => 'startEvent', 'next' => 'request'],
            ['id' => 'request', 'type' => 'userTask', 'name' => 'ثبت درخواست خرید',
             'assignee' => 'initiator', 'form' => 'purchase_form', 'next' => 'split'],
            ['id' => 'split', 'type' => 'parallelGateway', 'name' => 'بررسی موازی',
             'fork' => ['finance_review', 'warehouse_review']],
            ['id' => 'finance_review', 'type' => 'userTask', 'name' => 'بررسی مالی',
             'assignee' => 'role:finance', 'form' => 'finance_form', 'due' => 'P3D', 'next' => 'join'],
            ['id' => 'warehouse_review', 'type' => 'userTask', 'name' => 'بررسی انبار',
             'assignee' => 'role:warehouse', 'form' => 'warehouse_form', 'due' => 'P3D', 'next' => 'join'],
            ['id' => 'join', 'type' => 'parallelGateway', 'name' => 'جمع‌بندی', 'next' => 'decide'],
            ['id' => 'decide', 'type' => 'exclusiveGateway',
             'conditions' => [
                 ['expr' => 'variables.finance_ok && variables.warehouse_ok', 'next' => 'approved'],
                 ['default' => true, 'next' => 'rejected'],
             ]],
            ['id' => 'approved', 'type' => 'serviceTask', 'name' => 'ابلاغ تأیید',
             'action' => 'notify_initiator', 'next' => 'end'],
            ['id' => 'rejected', 'type' => 'serviceTask', 'name' => 'ابلاغ رد',
             'action' => 'notify_initiator', 'next' => 'end'],
            ['id' => 'end', 'type' => 'endEvent'],
        ],
        'timers' => [],
    ];

    $purchaseForms = [
        'purchase_form' => [
            'id' => 'purchase_form',
            'title' => 'فرم درخواست خرید',
            'fields' => [
                ['name' => 'item', 'type' => 'text', 'label' => 'کالا/خدمت', 'required' => true, 'max' => 190],
                ['name' => 'amount', 'type' => 'number', 'label' => 'مبلغ (ریال)', 'min' => 1, 'required' => true],
                ['name' => 'note', 'type' => 'textarea', 'label' => 'توضیح'],
            ],
        ],
        'finance_form' => [
            'id' => 'finance_form',
            'title' => 'بررسی مالی',
            'fields' => [
                ['name' => 'finance_ok', 'type' => 'checkbox', 'label' => 'تأیید مالی'],
                ['name' => 'finance_note', 'type' => 'textarea', 'label' => 'یادداشت مالی'],
            ],
        ],
        'warehouse_form' => [
            'id' => 'warehouse_form',
            'title' => 'بررسی انبار',
            'fields' => [
                ['name' => 'warehouse_ok', 'type' => 'checkbox', 'label' => 'تأیید انبار'],
                ['name' => 'warehouse_note', 'type' => 'textarea', 'label' => 'یادداشت انبار'],
            ],
        ],
    ];

    /** @var DesignerService $designer */
    $designer = app()->make(DesignerService::class);
    /** @var ProcessEngine $engine */
    $engine = app()->make(ProcessEngine::class);

    // کاربر دمو (core-user) — آغازگر نمونه‌ها
    $admin = DB::table('users')->where('email', 'admin@blog.test')->first();
    $initiatorId = $admin !== null ? (int) $admin['id'] : null;

    // ── ساخت/انتشار تعاریف (idempotent) ───────────────────────────────────
    $publish = static function (string $key, string $name, array $definition, array $forms) use ($designer): void {
        if (DB::table('bpms_process_definitions')->where('key', $key)->exists()) {
            return;
        }
        $draft = $designer->createDraft([
            'key' => $key,
            'name' => $name,
            'description' => 'فرایند نمونهٔ کیت BPMS',
            'definition' => $definition,
            'form_schema' => $forms,
        ]);
        $designer->publish($draft->id());
    };

    $publish('leave-request', 'درخواست مرخصی', $leaveDefinition, $leaveForms);
    $publish('purchase-approval', 'تأیید خرید (بررسی موازی)', $purchaseDefinition, $purchaseForms);

    if ($initiatorId === null) {
        return;
    }

    // ── نمونه‌های دمو (idempotent per business_key) ───────────────────────
    $hasInstance = static fn (string $businessKey): bool => DB::table('bpms_process_instances')
        ->where('business_key', $businessKey)->exists();

    // ۱) مرخصی ۱ روزه → auto-approve → completed (مسیر E2E-01)
    if (!$hasInstance('DEMO-LEAVE-AUTO')) {
        $instance = $engine->start('leave-request', [], $initiatorId, 'DEMO-LEAVE-AUTO');
        foreach ($engine->openTasksOf($instance->id()) as $task) {
            $engine->completeTask($task->id(), [
                'leave_type' => 'استحقاقی',
                'days' => 1,
                'from_date' => '1405/08/10',
                'to_date' => '1405/08/10',
                'reason' => 'مرخصی ساعتی — نمونهٔ دمو',
            ], $initiatorId, $engine->rolesOf($initiatorId));
        }
    }

    // ۲) مرخصی ۵ روزه → در انتظار تأیید مدیر (running + task + تایمر P2D)
    if (!$hasInstance('DEMO-LEAVE-MANAGER')) {
        $instance = $engine->start('leave-request', [], $initiatorId, 'DEMO-LEAVE-MANAGER');
        foreach ($engine->openTasksOf($instance->id()) as $task) {
            $engine->completeTask($task->id(), [
                'leave_type' => 'استحقاقی',
                'days' => 5,
                'from_date' => '1405/09/01',
                'to_date' => '1405/09/05',
                'reason' => 'سفر خانوادگی — نمونهٔ دمو',
            ], $initiatorId, $engine->rolesOf($initiatorId));
        }
    }

    // ۳) خرید → fork موازی (دو کار باز: مالی + انبار)
    if (!$hasInstance('DEMO-PURCHASE-01')) {
        $instance = $engine->start('purchase-approval', [], $initiatorId, 'DEMO-PURCHASE-01');
        foreach ($engine->openTasksOf($instance->id()) as $task) {
            if ($task->stateId() === 'request') {
                $engine->completeTask($task->id(), [
                    'item' => 'لپ‌تاپ ایستگاه کاری',
                    'amount' => 850_000_000,
                    'note' => 'برای تیم توسعه — نمونهٔ دمو',
                ], $initiatorId, $engine->rolesOf($initiatorId));
            }
        }
    }
};

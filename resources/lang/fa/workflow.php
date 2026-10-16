<?php

declare(strict_types=1);

/*
 * کلیدهای ترجمهٔ موتور Workflow (DOC-071) — fa
 */

return [
    'not_found' => 'گردش‌کار «:workflow» تعریف نشده است',
    'process_not_found' => 'فرایند «:instance» یافت نشد',
    'not_running' => 'فرایند «:instance» در حال اجرا نیست (وضعیت: :status)',
    'illegal_transition' => 'گذار «:transition» از وضعیت «:from» مجاز نیست',
    'unknown_transition' => 'گذار «:transition» در تعریف فرایند وجود ندارد',
    'gateway_stuck' => 'دروازهٔ «:gateway» هیچ گذار خروجی قابل‌اجرا ندارد',
    'gateway_manual_fire' => 'گذارهای خروجیِ دروازهٔ «:gateway» فقط توسط موتور اجرا می‌شوند',
    'join_unbalanced' => 'دروازهٔ الحاق «:gateway» نامتوازن است (:arrived از :expected توکن)',
    'unauthorized' => 'کاربر جاری مجوز گذار «:transition» را ندارد',
    'invalid_duration' => 'مدت «:after» نامعتبر است (ISO-8601 مثل P3D یا انسانی مثل «3 days»)',
    'max_instances' => 'سقف instanceهای فعال گردش‌کار «:workflow» (:limit) پر شده است',
    'timer_not_found' => 'تایمر «:timer» روی فرایند «:instance» فعال نیست',
    'timer_load_failed' => 'بارگذاری فرایند برای اجرای تایمر ناموفق بود',
    'timer_failed' => 'اجرای تایمر «:timer» ناموفق بود',
    'task_not_found' => 'کار «:task» یافت نشد',
    'task_not_pending' => 'کار «:task» در وضعیت :status است (فقط pending/claimed قابل عملیات)',
    'task_not_claimable' => 'کاربر assignee/candidate کار «:task» نیست',
    'task_no_outgoing' => 'وضعیت «:state» هیچ گذار خروجی ندارد',
    'task_ambiguous_outgoing' => 'وضعیت «:state» چند گذار خروجی دارد — transitionId را صریح مشخص کنید',
    'definition' => [
        'missing_start' => 'فرایند «:process» هیچ state شروعی ندارد',
        'duplicate_state' => 'state تکراری «:state» در فرایند «:process»',
        'duplicate_transition' => 'گذار تکراری «:transition» در فرایند «:process»',
        'unknown_state' => 'state ناشناخته «:state» در فرایند «:process» (:where)',
        'invalid_id' => 'شناسهٔ نامعتبر «:id» در فرایند «:process»',
        'orphan_state' => 'state «:state» در فرایند «:process» هیچ گذار ورودی/خروجی ندارد',
        'subprocess_missing' => 'زیرفرایند «:state» در فرایند «:process» ارجاع process ندارد',
    ],
];

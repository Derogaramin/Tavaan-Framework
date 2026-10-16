<?php

declare(strict_types=1);

/*
 * کلیدهای ترجمهٔ موتور Payment (DOC-073) — fa
 */

return [
    'gateway_not_found' => 'درگاه پرداخت «:gateway» شناخته نشد',
    'gateway_not_configured' => 'درگاه «:gateway» پیکربندی نشده است (:missing)',
    'gateway_error' => 'پاسخ نامعتبر از درگاه «:gateway» در عملیات :operation',
    'gateway_connection_failed' => 'خطای اتصال به درگاه «:gateway» در عملیات :operation',
    'not_found' => 'تراکنش پرداخت یافت نشد',
    'already_paid' => 'تراکنش «:transaction» پیش‌تر با موفقیت پرداخت شده است',
    'not_refundable' => 'تراکنش «:transaction» با وضعیت :status قابل بازگشت نیست',
    'unsupported_operation' => 'عملیات :operation در درگاه «:gateway» پشتیبانی نمی‌شود',
    'invalid_amount' => 'مبلغ نامعتبر: :reason',
    'order_id_required' => 'شناسهٔ سفارش (orderId) برای شروع پرداخت الزامی است',
    'signature_mismatch' => 'امضای درخواست IPN درگاه «:gateway» نامعتبر است',
];

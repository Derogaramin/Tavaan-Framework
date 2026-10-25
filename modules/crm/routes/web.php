<?php

declare(strict_types=1);

/*
 * ماژول CRM — مسیرهای وب (DOC-096 §3.3/§7).
 * نام‌گذاری با prefix «crm.» (DoD §5).
 *
 * پرتال پشتیبانی عمومی است (مهمان)؛ نوشتن‌ها Throttle (ضداسپم — الگوی forum).
 */

use Modules\Crm\Http\Controllers\SupportPortalController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Security\Middleware\ThrottleMiddleware;

// ── پرتال پشتیبانی (عمومی) ─────────────────────────────────────────────────
Route::get('/crm/support', [SupportPortalController::class, 'create'])->name('crm.support.create');
Route::get('/crm/support/track', [SupportPortalController::class, 'track'])->name('crm.support.track');
Route::post('/crm/support', [SupportPortalController::class, 'store'])
    ->middleware([ThrottleMiddleware::class . ':5,60'])
    ->name('crm.support.store');
Route::get('/crm/support/{number}', [SupportPortalController::class, 'show'])->name('crm.support.show');
Route::post('/crm/support/{number}/replies', [SupportPortalController::class, 'reply'])
    ->middleware([ThrottleMiddleware::class . ':10,60'])
    ->name('crm.support.reply');

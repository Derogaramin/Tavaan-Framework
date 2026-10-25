<?php

declare(strict_types=1);

/*
 * ماژول CRM — API یکپارچگی (DOC-096 §7: فرم وب‌سایت → lead).
 *
 * 🔒 Throttle روی ورودی‌های عمومی (ضداسپم/DoS) — ThrottleMiddleware موتور
 * security (ارجاع در routes مجاز — الگوی forum).
 */

use Modules\Crm\Http\Controllers\Api\ApiCrmController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Security\Middleware\ThrottleMiddleware;

Route::group(['prefix' => '/api/v1/crm'], static function (): void {
    Route::post('/leads', [ApiCrmController::class, 'createLead'])
        ->middleware([ThrottleMiddleware::class . ':10,60'])
        ->name('api.crm.leads');

    Route::post('/tickets', [ApiCrmController::class, 'createTicket'])
        ->middleware([ThrottleMiddleware::class . ':10,60'])
        ->name('api.crm.tickets');

    Route::get('/tickets/{number}', [ApiCrmController::class, 'ticketStatus'])
        ->middleware([ThrottleMiddleware::class . ':30,60'])
        ->name('api.crm.ticket.status');
});

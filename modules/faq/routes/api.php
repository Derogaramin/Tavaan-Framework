<?php

declare(strict_types=1);

/*
 * ماژول پرسش‌های متداول — روت‌های API (JSON عمومی).
 */

use Modules\Faq\Http\Controllers\FaqController;
use Tavaan\Core\Facade\Facades\Route;

Route::get('/api/faqs', [FaqController::class, 'apiIndex'])->name('api.faqs.index');

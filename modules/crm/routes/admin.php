<?php

declare(strict_types=1);

/*
 * ماژول CRM — مسیرهای پنل مدیریت (DOC-096 §4/§6).
 *
 * middleware: احراز هویت (session guard) — مجوزهای ریز (Gate/Policy) در
 * کنترلرها بررسی می‌شوند (الگوی forum/shop/lms).
 */

use Modules\Crm\Http\Controllers\Admin\ActivityAdminController;
use Modules\Crm\Http\Controllers\Admin\CampaignAdminController;
use Modules\Crm\Http\Controllers\Admin\ContactAdminController;
use Modules\Crm\Http\Controllers\Admin\DashboardController;
use Modules\Crm\Http\Controllers\Admin\LeadAdminController;
use Modules\Crm\Http\Controllers\Admin\OpportunityAdminController;
use Modules\Crm\Http\Controllers\Admin\ReportAdminController;
use Modules\Crm\Http\Controllers\Admin\TicketAdminController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group(['prefix' => '/admin/crm', 'middleware' => [AuthenticateMiddleware::class]], static function (): void {
    // داشبورد
    Route::get('', [DashboardController::class, 'index'])->name('admin.crm.dashboard');
    Route::get('/', [DashboardController::class, 'index'])->name('admin.crm.dashboard.slash');

    // مخاطبان
    Route::get('/contacts', [ContactAdminController::class, 'index'])->name('admin.crm.contacts');
    Route::get('/contacts/create', [ContactAdminController::class, 'create'])->name('admin.crm.contacts.create');
    Route::post('/contacts', [ContactAdminController::class, 'store'])->name('admin.crm.contacts.store');
    Route::get('/contacts/{id}', [ContactAdminController::class, 'show'])->name('admin.crm.contacts.show');
    Route::get('/contacts/{id}/edit', [ContactAdminController::class, 'edit'])->name('admin.crm.contacts.edit');
    Route::post('/contacts/{id}', [ContactAdminController::class, 'update'])->name('admin.crm.contacts.update');
    Route::post('/contacts/{id}/assign', [ContactAdminController::class, 'assign'])->name('admin.crm.contacts.assign');
    Route::delete('/contacts/{id}', [ContactAdminController::class, 'destroy'])->name('admin.crm.contacts.destroy');

    // سرنخ‌ها
    Route::get('/leads', [LeadAdminController::class, 'index'])->name('admin.crm.leads');
    Route::get('/leads/create', [LeadAdminController::class, 'create'])->name('admin.crm.leads.create');
    Route::post('/leads', [LeadAdminController::class, 'store'])->name('admin.crm.leads.store');
    Route::get('/leads/{id}', [LeadAdminController::class, 'show'])->name('admin.crm.leads.show');
    Route::post('/leads/{id}', [LeadAdminController::class, 'update'])->name('admin.crm.leads.update');
    Route::post('/leads/{id}/qualify', [LeadAdminController::class, 'qualify'])->name('admin.crm.leads.qualify');
    Route::post('/leads/{id}/convert', [LeadAdminController::class, 'convert'])->name('admin.crm.leads.convert');
    Route::post('/leads/{id}/lost', [LeadAdminController::class, 'lost'])->name('admin.crm.leads.lost');
    Route::post('/leads/{id}/assign', [LeadAdminController::class, 'assign'])->name('admin.crm.leads.assign');

    // فرصت‌ها (pipeline)
    Route::get('/opportunities', [OpportunityAdminController::class, 'index'])->name('admin.crm.opportunities');
    Route::get('/opportunities/create', [OpportunityAdminController::class, 'create'])->name('admin.crm.opportunities.create');
    Route::post('/opportunities', [OpportunityAdminController::class, 'store'])->name('admin.crm.opportunities.store');
    Route::get('/opportunities/{id}', [OpportunityAdminController::class, 'show'])->name('admin.crm.opportunities.show');
    Route::post('/opportunities/{id}', [OpportunityAdminController::class, 'update'])->name('admin.crm.opportunities.update');
    Route::post('/opportunities/{id}/move', [OpportunityAdminController::class, 'move'])->name('admin.crm.opportunities.move');
    Route::post('/opportunities/{id}/assign', [OpportunityAdminController::class, 'assign'])->name('admin.crm.opportunities.assign');

    // تیکت‌ها
    Route::get('/tickets', [TicketAdminController::class, 'index'])->name('admin.crm.tickets');
    Route::get('/tickets/{id}', [TicketAdminController::class, 'show'])->name('admin.crm.tickets.show');
    Route::post('/tickets/{id}/assign', [TicketAdminController::class, 'assign'])->name('admin.crm.tickets.assign');
    Route::post('/tickets/{id}/priority', [TicketAdminController::class, 'priority'])->name('admin.crm.tickets.priority');
    Route::post('/tickets/{id}/replies', [TicketAdminController::class, 'reply'])->name('admin.crm.tickets.reply');
    Route::post('/tickets/{id}/resolve', [TicketAdminController::class, 'resolve'])->name('admin.crm.tickets.resolve');
    Route::post('/tickets/{id}/close', [TicketAdminController::class, 'close'])->name('admin.crm.tickets.close');

    // فعالیت‌ها/کارها
    Route::get('/activities', [ActivityAdminController::class, 'index'])->name('admin.crm.activities');
    Route::post('/activities', [ActivityAdminController::class, 'store'])->name('admin.crm.activities.store');
    Route::post('/activities/{id}/complete', [ActivityAdminController::class, 'complete'])->name('admin.crm.activities.complete');

    // کمپین‌ها
    Route::get('/campaigns', [CampaignAdminController::class, 'index'])->name('admin.crm.campaigns');
    Route::get('/campaigns/create', [CampaignAdminController::class, 'create'])->name('admin.crm.campaigns.create');
    Route::post('/campaigns', [CampaignAdminController::class, 'store'])->name('admin.crm.campaigns.store');
    Route::get('/campaigns/{id}/edit', [CampaignAdminController::class, 'edit'])->name('admin.crm.campaigns.edit');
    Route::post('/campaigns/{id}', [CampaignAdminController::class, 'update'])->name('admin.crm.campaigns.update');
    Route::post('/campaigns/{id}/send', [CampaignAdminController::class, 'send'])->name('admin.crm.campaigns.send');

    // گزارش‌ها (DOC-096 §6)
    Route::get('/reports', [ReportAdminController::class, 'index'])->name('admin.crm.reports');
    Route::get('/reports/pipeline.csv', [ReportAdminController::class, 'csv'])->name('admin.crm.reports.csv');
});

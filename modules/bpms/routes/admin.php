<?php

declare(strict_types=1);

/*
 * ماژول BPMS — مسیرهای پنل مدیریت (DOC-098 §1.1/§8).
 *
 * middleware: احراز هویت (session guard) — مجوزهای ریز (Gate/Policy) در
 * کنترلرها بررسی می‌شوند (الگوی crm/erp).
 */

use Modules\Bpms\Http\Controllers\Admin\AuditController;
use Modules\Bpms\Http\Controllers\Admin\DefinitionController;
use Modules\Bpms\Http\Controllers\Admin\InstanceController;
use Modules\Bpms\Http\Controllers\Admin\MonitoringController;
use Modules\Bpms\Http\Controllers\Admin\TaskAdminController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group(['prefix' => '/admin/bpms', 'middleware' => [AuthenticateMiddleware::class]], static function (): void {
    // داشبورد پایش (§8)
    Route::get('', [MonitoringController::class, 'index'])->name('admin.bpms.monitoring');
    Route::get('/', [MonitoringController::class, 'index'])->name('admin.bpms.monitoring.slash');
    Route::get('/reports/kpis.csv', [MonitoringController::class, 'csv'])->name('admin.bpms.reports.csv');

    // فرایندساز (Designer — §1.1)
    Route::get('/definitions', [DefinitionController::class, 'index'])->name('admin.bpms.definitions');
    Route::get('/definitions/create', [DefinitionController::class, 'create'])->name('admin.bpms.definitions.create');
    Route::post('/definitions', [DefinitionController::class, 'store'])->name('admin.bpms.definitions.store');
    Route::get('/definitions/{id}', [DefinitionController::class, 'show'])->name('admin.bpms.definitions.show');
    Route::get('/definitions/{id}/edit', [DefinitionController::class, 'edit'])->name('admin.bpms.definitions.edit');
    Route::get('/definitions/{id}/diagram.svg', [DefinitionController::class, 'diagram'])->name('admin.bpms.definitions.diagram');
    Route::post('/definitions/{id}', [DefinitionController::class, 'update'])->name('admin.bpms.definitions.update');
    Route::post('/definitions/{id}/publish', [DefinitionController::class, 'publish'])->name('admin.bpms.definitions.publish');
    Route::post('/definitions/{id}/activate', [DefinitionController::class, 'activate'])->name('admin.bpms.definitions.activate');
    Route::post('/definitions/{id}/retire', [DefinitionController::class, 'retire'])->name('admin.bpms.definitions.retire');

    // نمونه‌های فرایند (§2)
    Route::get('/instances', [InstanceController::class, 'index'])->name('admin.bpms.instances');
    Route::get('/instances/create', [InstanceController::class, 'create'])->name('admin.bpms.instances.create');
    Route::post('/instances', [InstanceController::class, 'store'])->name('admin.bpms.instances.store');
    Route::get('/instances/{id}', [InstanceController::class, 'show'])->name('admin.bpms.instances.show');
    Route::post('/instances/{id}/terminate', [InstanceController::class, 'terminate'])->name('admin.bpms.instances.terminate');
    Route::post('/instances/{id}/suspend', [InstanceController::class, 'suspend'])->name('admin.bpms.instances.suspend');
    Route::post('/instances/{id}/resume', [InstanceController::class, 'resume'])->name('admin.bpms.instances.resume');

    // کارها (نمای مدیر)
    Route::get('/tasks', [TaskAdminController::class, 'index'])->name('admin.bpms.tasks');
    Route::post('/tasks/{id}/assign', [TaskAdminController::class, 'assign'])->name('admin.bpms.tasks.assign');
    Route::post('/tasks/{id}/complete', [TaskAdminController::class, 'complete'])->name('admin.bpms.tasks.complete');

    // ردپای audit (E2E-09)
    Route::get('/audit', [AuditController::class, 'index'])->name('admin.bpms.audit');
});

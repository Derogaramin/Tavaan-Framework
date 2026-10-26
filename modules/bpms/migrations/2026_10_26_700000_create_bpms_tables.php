<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول ماژول BPMS — DOC-098 §2.
 *
 * prefix ماژول = bpms_ (DoD §3). شناسه‌ها ULID char(26) (استاندارد فریمورک).
 *
 * افزودنی‌های مستند نسبت به DOC-098 §2:
 * - definitions: status (draft/published/retired) + description + created_by +
 *   updated_at — چرخهٔ انتشار فرایندساز (§1.1) و audit مدیریتی
 * - instances: definition_key (denormalized برای فهرست‌ها) + workflow_instance_id
 *   (نمونهٔ mirror موتور workflow — تاریخچه/visualization، الگوی LMS/CRM/ERP)
 * - tasks: form_id (کلید فرم در form_schema تعریف) + claimed_by/completed_by +
 *   created_at + escalated_at/escalated_to (تایمر/escalation §9) + comment
 * - history: trigger (user|system|timer) — تفکیک بازیگر برای پایش/audit
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bpms_process_definitions')) {
            Schema::create('bpms_process_definitions', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                       // ULID
                $t->string('key', 100);
                $t->string('name', 190);
                $t->unsignedInteger('version')->default(1);
                $t->json('definition');                              // گراف elements/timers (§3)
                $t->json('form_schema')->nullable();                 // formId → schema (§4)
                $t->string('status', 20)->default('draft');          // draft|published|retired
                $t->boolean('is_active')->default(false);
                $t->string('description', 500)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->unique(['key', 'version'], 'uk_bpms_def_key_version');
                $t->index(['key', 'is_active'], 'idx_bpms_def_key_active');
            });
        }

        if (!Schema::hasTable('bpms_process_instances')) {
            Schema::create('bpms_process_instances', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('definition_id', 26);
                $t->string('definition_key', 100);
                $t->string('business_key', 100)->nullable();         // ارجاع به موجودیت کسب‌وکار
                $t->unsignedBigInteger('initiator_id')->nullable();
                $t->string('current_state', 100)->default('');
                $t->string('status', 20)->default('running');        // running|completed|suspended|terminated
                $t->json('variables')->nullable();                   // + کلیدهای رزرو _tokens/_joins/_ended
                $t->char('workflow_instance_id', 26)->nullable();    // mirror موتور workflow
                $t->dateTime('started_at', 3);
                $t->dateTime('completed_at', 3)->nullable();
                $t->index(['definition_id', 'status'], 'idx_bpms_def_status');
                $t->index(['definition_key', 'status'], 'idx_bpms_key_status');
                $t->index(['initiator_id'], 'idx_bpms_initiator');
                $t->index(['business_key'], 'idx_bpms_business_key');
            });
        }

        if (!Schema::hasTable('bpms_tasks')) {
            Schema::create('bpms_tasks', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('instance_id', 26);
                $t->string('state_id', 100);
                $t->string('title', 255);
                $t->string('assignee_type', 30)->default('anyone');  // user|role|group|initiator|anyone
                $t->string('assignee_id', 64)->nullable();
                $t->string('form_id', 100)->nullable();
                $t->json('form_data')->nullable();
                $t->string('status', 20)->default('pending');        // pending|claimed|completed|canceled
                $t->string('priority', 20)->default('normal');
                $t->dateTime('due_at', 3)->nullable();
                $t->unsignedBigInteger('claimed_by')->nullable();
                $t->unsignedBigInteger('completed_by')->nullable();
                $t->string('comment', 1000)->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('claimed_at', 3)->nullable();
                $t->dateTime('completed_at', 3)->nullable();
                $t->dateTime('escalated_at', 3)->nullable();
                $t->string('escalated_to', 100)->nullable();
                $t->index(['assignee_id', 'status'], 'idx_bpms_task_assignee');
                $t->index(['instance_id'], 'idx_bpms_task_instance');
                $t->index(['status', 'due_at'], 'idx_bpms_task_due');
            });
        }

        if (!Schema::hasTable('bpms_history')) {
            Schema::create('bpms_history', function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->char('instance_id', 26);
                $t->string('from_state', 100)->nullable();
                $t->string('to_state', 100);
                $t->string('transition', 100);
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->string('trigger', 20)->default('system');        // user|system|timer
                $t->dateTime('occurred_at', 3);
                $t->integer('duration_ms')->nullable();
                $t->index(['instance_id'], 'idx_bpms_history_instance');
                $t->index(['occurred_at'], 'idx_bpms_history_time');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bpms_history');
        Schema::dropIfExists('bpms_tasks');
        Schema::dropIfExists('bpms_process_instances');
        Schema::dropIfExists('bpms_process_definitions');
    }
};

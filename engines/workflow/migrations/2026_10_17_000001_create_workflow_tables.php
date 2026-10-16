<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema as SchemaFacade;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول موتور Workflow — DOC-071 §5:
 * tvn_workflows / tvn_process_instances / tvn_process_tasks / tvn_process_history
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!SchemaFacade::hasTable('tvn_workflows')) {
            SchemaFacade::create('tvn_workflows', function (Blueprint $t): void {
                $t->char('id', 26)->primary();              // ULID (ADR-0006)
                $t->string('name', 100);
                $t->integer('version')->default(1);
                $t->json('definition');
                $t->boolean('is_active')->default(true);
                $t->dateTime('created_at', 3);
                $t->index(['name', 'is_active']);
            });
        }

        if (!SchemaFacade::hasTable('tvn_process_instances')) {
            SchemaFacade::create('tvn_process_instances', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('workflow_id', 26);
                $t->string('subject_type', 190)->nullable();
                $t->string('subject_id', 64)->nullable();
                $t->string('current_state', 100);
                $t->string('status', 20);
                $t->json('variables')->nullable();
                $t->dateTime('started_at', 3)->nullable();
                $t->dateTime('completed_at', 3)->nullable();
                $t->index(['subject_type', 'subject_id']);
                $t->index('status');
                $t->index('workflow_id');
            });
        }

        if (!SchemaFacade::hasTable('tvn_process_tasks')) {
            SchemaFacade::create('tvn_process_tasks', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('instance_id', 26);
                $t->string('state_id', 100);
                $t->string('assignee_type', 50)->nullable();
                $t->string('assignee_id', 64)->nullable();
                $t->string('status', 20)->default('pending');
                $t->dateTime('due_at', 3)->nullable();
                $t->dateTime('completed_at', 3)->nullable();
                $t->index('instance_id');
                $t->index(['assignee_id', 'status']);
            });
        }

        if (!SchemaFacade::hasTable('tvn_process_history')) {
            SchemaFacade::create('tvn_process_history', function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->char('instance_id', 26);
                $t->string('from_state', 100)->nullable();
                $t->string('to_state', 100)->nullable();
                $t->string('transition', 100)->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->dateTime('occurred_at', 3);
                $t->index('instance_id');
            });
        }
    }

    public function down(): void
    {
        SchemaFacade::dropIfExists('tvn_process_history');
        SchemaFacade::dropIfExists('tvn_process_tasks');
        SchemaFacade::dropIfExists('tvn_process_instances');
        SchemaFacade::dropIfExists('tvn_workflows');
    }
};

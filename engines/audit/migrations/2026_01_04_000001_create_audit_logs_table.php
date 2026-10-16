<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جدول tvn_audit_logs — زنجیرهٔ هش ممیزی (DOC-058 §2).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tvn_audit_logs')) {
            return;
        }

        Schema::create('tvn_audit_logs', function (Blueprint $t): void {
            $t->id();
            $t->char('audit_id', 26)->unique();
            $t->string('event', 50);
            $t->string('auditable_type', 190);
            $t->string('auditable_id', 64);
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->text('old_values')->nullable();
            $t->text('new_values')->nullable();
            $t->text('changed_fields')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->char('trace_id', 32)->nullable();
            $t->char('prev_hash', 64);
            $t->char('hash', 64);
            $t->string('created_at', 32)->index();
            $t->index(['auditable_type', 'auditable_id'], 'idx_auditable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tvn_audit_logs');
    }
};

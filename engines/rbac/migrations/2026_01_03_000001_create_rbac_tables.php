<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول RBAC — DOC-057 §2.1 (tvn_permissions/tvn_roles/tvn_role_user/tvn_permission_role/tvn_delegations).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tvn_permissions')) {
            Schema::create('tvn_permissions', function (Blueprint $t): void {
                $t->id();
                $t->string('module', 64);
                $t->string('key', 190)->unique();
                $t->string('title_fa', 190)->nullable();
                $t->string('title_en', 190)->nullable();
                $t->string('group_id', 64)->nullable();
                $t->timestamp('created_at', 3)->nullable();
            });
        }

        if (!Schema::hasTable('tvn_roles')) {
            Schema::create('tvn_roles', function (Blueprint $t): void {
                $t->id();
                $t->string('key', 64)->unique();
                $t->string('name', 190);
                $t->unsignedBigInteger('parent_id')->nullable();
                $t->boolean('is_system')->default(false);
                $t->string('scope', 32)->default('global');
                $t->timestamp('created_at', 3)->nullable();
            });
        }

        if (!Schema::hasTable('tvn_role_user')) {
            Schema::create('tvn_role_user', function (Blueprint $t): void {
                $t->unsignedBigInteger('role_id');
                $t->string('user_id', 64);
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->timestamp('expires_at', 3)->nullable();   // 🔒 نقش موقت
                $t->unsignedBigInteger('granted_by')->nullable();
                $t->index(['user_id', 'role_id'], 'idx_role_user');
            });
        }

        if (!Schema::hasTable('tvn_permission_role')) {
            Schema::create('tvn_permission_role', function (Blueprint $t): void {
                $t->unsignedBigInteger('permission_id');
                $t->unsignedBigInteger('role_id');
                $t->primary(['permission_id', 'role_id']);
            });
        }

        if (!Schema::hasTable('tvn_delegations')) {
            Schema::create('tvn_delegations', function (Blueprint $t): void {
                $t->id();
                $t->string('from_user_id', 64);
                $t->string('to_user_id', 64)->index();
                $t->text('permissions');
                $t->timestamp('starts_at', 3);
                $t->timestamp('ends_at', 3);
                $t->string('reason')->nullable();
                $t->timestamp('created_at', 3)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tvn_delegations');
        Schema::dropIfExists('tvn_permission_role');
        Schema::dropIfExists('tvn_role_user');
        Schema::dropIfExists('tvn_roles');
        Schema::dropIfExists('tvn_permissions');
    }
};

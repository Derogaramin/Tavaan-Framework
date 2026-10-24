<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جدول users — ماژول core-user (DOC-080 database/migrations، DOC-090 §5).
 * ستون‌ها هم‌راستا با DatabaseUserProvider موتور auth (table=users) و RBAC (user_id).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $t): void {
                $t->id();
                $t->string('name', 190);
                $t->string('email', 190)->unique();
                $t->string('mobile', 20)->nullable();
                $t->string('password', 255);
                $t->text('bio')->nullable();
                $t->char('avatar', 26)->nullable();      // → tvn_media (ماژول media)
                $t->string('status', 20)->default('active');   // active|inactive|banned
                $t->timestamp('email_verified_at', 3)->nullable();
                $t->string('remember_token', 100)->nullable();
                $t->timestamp('last_login_at', 3)->nullable();
                $t->timestamps(3);
                $t->index(['status'], 'idx_users_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول موتور Auth — password_resets / personal_access_tokens / user_devices / two_factor_secrets (DOC-056).
 */

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $t): void {
                $t->string('email', 190)->primary();
                $t->string('token', 64);
                $t->timestamp('created_at', 3)->nullable();
            });
        }

        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $t): void {
                $t->id();
                $t->string('user_id', 64)->index();
                $t->string('jti', 64)->unique();
                $t->string('family', 64)->index();
                $t->string('type', 16);           // access|refresh
                $t->string('scopes', 255)->default('[]');
                $t->boolean('used')->default(false);
                $t->boolean('revoked')->default(false);
                $t->unsignedBigInteger('expires_at')->index();
                $t->timestamp('created_at', 3)->nullable();
            });
        }

        if (!Schema::hasTable('user_devices')) {
            Schema::create('user_devices', function (Blueprint $t): void {
                $t->id();
                $t->string('user_id', 64)->index();
                $t->string('fingerprint', 64);
                $t->string('ip_address', 45)->nullable();
                $t->string('user_agent')->nullable();
                $t->boolean('revoked')->default(false);
                $t->timestamp('last_used_at', 3)->nullable();
                $t->timestamp('created_at', 3)->nullable();
            });
        }

        if (!Schema::hasTable('two_factor_secrets')) {
            Schema::create('two_factor_secrets', function (Blueprint $t): void {
                $t->string('user_id', 64)->primary();
                $t->text('secret');                 // رمزنگاری‌شده
                $t->text('recovery_codes')->nullable();   // JSON هش‌ها
                $t->timestamp('confirmed_at', 3)->nullable();
                $t->timestamp('created_at', 3)->nullable();
                $t->timestamp('updated_at', 3)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_secrets');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('password_resets');
    }
};

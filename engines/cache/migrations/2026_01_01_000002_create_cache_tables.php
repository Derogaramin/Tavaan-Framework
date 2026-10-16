<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول cache و cache_locks — درایور database موتور Cache (DOC-060).
 */

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $t): void {
                $t->string('cache_key', 190)->unique();
                $t->text('cache_value');
                $t->unsignedBigInteger('expires_at')->index();
            });
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $t): void {
                $t->string('lock_key', 190)->primary();
                $t->string('lock_owner', 64);
                $t->unsignedBigInteger('expires_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};

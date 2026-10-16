<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جدول sessions — درایور database نشست (DOC-041؛ تکمیل در M3).
 */

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $t): void {
            $t->string('id', 64)->primary();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->text('payload');
            $t->unsignedBigInteger('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};

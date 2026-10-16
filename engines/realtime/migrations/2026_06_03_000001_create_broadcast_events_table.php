<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جدول tvn_broadcast_events — broker دیتابیسی موتور Realtime (DOC-045 §6).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tvn_broadcast_events')) {
            Schema::create('tvn_broadcast_events', function (Blueprint $t): void {
                $t->id();
                $t->string('channel', 200)->index();
                $t->string('name', 190);
                $t->text('payload');                    // JSON
                $t->dateTime('created_at', 3)->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tvn_broadcast_events');
    }
};

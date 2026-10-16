<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جدول tvn_notifications — کانال database آگاه‌سازی In-App (DOC-066 §4).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tvn_notifications')) {
            Schema::create('tvn_notifications', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('type', 190);
                $t->string('notifiable_type', 190);
                $t->string('notifiable_id', 64);
                $t->json('data');
                $t->dateTime('read_at', 3)->nullable();
                $t->dateTime('created_at', 3);
                $t->index(['notifiable_type', 'notifiable_id', 'read_at'], 'idx_notifiable');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tvn_notifications');
    }
};

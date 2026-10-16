<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جدول tvn_media — کتابخانهٔ رسانه (DOC-074 §6).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tvn_media')) {
            Schema::create('tvn_media', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('disk', 64);
                $t->string('path', 500);
                $t->string('original_name', 255)->nullable();
                $t->string('mime_type', 100);
                $t->unsignedBigInteger('size');
                $t->integer('width')->nullable();
                $t->integer('height')->nullable();
                $t->char('hash', 64)->index();          // SHA-256 برای dedup
                $t->string('mediable_type', 190)->nullable();
                $t->string('mediable_id', 64)->nullable();
                $t->json('variants')->nullable();       // {thumb:{path,w,h}, …}
                $t->json('meta')->nullable();           // EXIF (سانیتیزه‌شده)
                $t->dateTime('created_at', 3);
                $t->index(['mediable_type', 'mediable_id'], 'idx_mediable');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tvn_media');
    }
};

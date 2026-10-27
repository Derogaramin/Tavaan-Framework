<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول ماژول پرسش‌های متداول — faq_categories + faq_items.
 * prefix ماژول = faq_ (DoD ماژول — DOC-113 §3).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('faq_categories')) {
            Schema::create('faq_categories', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                  // ULID
                $t->string('slug', 200)->unique();
                $t->string('title', 255);
                $t->integer('sort_order')->default(0);
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->dateTime('deleted_at', 3)->nullable();      // soft delete
            });
        }

        if (!Schema::hasTable('faq_items')) {
            Schema::create('faq_items', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                  // ULID
                $t->char('category_id', 26)->nullable();
                $t->string('question', 500);
                $t->text('answer');                             // HTML پاک‌سازی‌شده
                $t->string('status', 20)->default('draft');     // draft|published|archived
                $t->integer('sort_order')->default(0);
                $t->integer('helpful_yes')->default(0);
                $t->integer('helpful_no')->default(0);
                $t->unsignedBigInteger('author_id');
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->dateTime('deleted_at', 3)->nullable();      // soft delete
                $t->index(['status', 'sort_order'], 'idx_faq_items_status_sort');
                $t->index(['category_id'], 'idx_faq_items_category');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_items');
        Schema::dropIfExists('faq_categories');
    }
};

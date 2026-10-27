<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول ماژول نظرسنجی — poll_polls + poll_votes.
 * prefix ماژول = poll_ (DoD ماژول — DOC-113 §3).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('poll_polls')) {
            Schema::create('poll_polls', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                  // ULID
                $t->string('slug', 200)->unique();
                $t->string('question', 500);
                $t->text('options');                            // JSON: list<string>
                $t->boolean('multiple')->default(false);        // چندانتخابی؟
                $t->string('status', 20)->default('draft');     // draft|open|closed
                $t->dateTime('starts_at', 3)->nullable();
                $t->dateTime('ends_at', 3)->nullable();
                $t->unsignedBigInteger('author_id');
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->dateTime('deleted_at', 3)->nullable();      // soft delete
                $t->index(['status', 'ends_at'], 'idx_polls_status_ends');
            });
        }

        if (!Schema::hasTable('poll_votes')) {
            Schema::create('poll_votes', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                  // ULID
                $t->char('poll_id', 26);
                $t->integer('option_index');                    // اندیس گزینه (از صفر)
                $t->unsignedBigInteger('user_id')->nullable();
                $t->char('ip_hash', 64)->nullable();            // sha256(ip|app.key)
                $t->dateTime('created_at', 3);
                $t->index(['poll_id', 'user_id'], 'idx_votes_poll_user');
                $t->index(['poll_id', 'ip_hash'], 'idx_votes_poll_ip');
                $t->index(['poll_id'], 'idx_votes_poll');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_polls');
    }
};

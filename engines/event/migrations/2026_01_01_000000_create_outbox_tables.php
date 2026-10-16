<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول tvn_outbox/tvn_inbox — الگوی Transactional Outbox (ADR-0009 / DOC-033 §5.3).
 */

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tvn_outbox')) {
            Schema::create('tvn_outbox', function (Blueprint $t): void {
                $t->id();
                $t->ulid('event_id')->unique();
                $t->string('event_name', 190)->index();
                $t->text('payload')->nullable();
                $t->text('headers')->nullable();
                $t->char('trace_id', 32)->nullable();
                $t->string('status', 16)->default('pending')->index();
                $t->string('owner', 32)->nullable();
                $t->unsignedInteger('attempts')->default(0);
                $t->unsignedBigInteger('available_at')->index();
                $t->string('claimed_at', 32)->nullable();
                $t->string('processed_at', 32)->nullable();
                $t->string('created_at', 32);
            });
        }

        if (!Schema::hasTable('tvn_inbox')) {
            Schema::create('tvn_inbox', function (Blueprint $t): void {
                $t->string('idempotency_key', 128)->primary();
                $t->string('consumer', 64);
                $t->string('processed_at', 32);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tvn_inbox');
        Schema::dropIfExists('tvn_outbox');
    }
};

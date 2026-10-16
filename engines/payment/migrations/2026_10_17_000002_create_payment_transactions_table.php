<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جدول tvn_payment_transactions — DOC-073 §6.
 * 🔒 amount BIGINT (minor unit — هرگز float؛ ADR-0013)
 * 🔒 card_pan فقط mask‌شده
 * 🔒 uk_idem: کلید idempotency یکتا (ضد replay)
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tvn_payment_transactions')) {
            Schema::create('tvn_payment_transactions', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                 // ULID (ADR-0006)
                $t->string('gateway', 50);
                $t->string('order_type', 190)->nullable();
                $t->string('order_id', 64)->nullable();
                $t->bigInteger('amount');                      // minor unit
                $t->char('currency', 3);
                $t->string('authority', 100)->nullable();
                $t->string('reference_id', 100)->nullable();
                $t->string('status', 20);                      // pending|success|failed|canceled|refunded
                $t->string('card_pan', 30)->nullable();        // 🔒 masked
                $t->json('callback_payload')->nullable();
                $t->char('idempotency_key', 64)->unique();
                $t->dateTime('paid_at', 3)->nullable();
                $t->dateTime('created_at', 3);

                $t->index(['gateway', 'authority']);
                $t->index(['order_type', 'order_id']);
                $t->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tvn_payment_transactions');
    }
};

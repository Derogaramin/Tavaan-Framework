<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جدول tvn_tenants — ثبت مرکزی tenantها (DOC-028 §2.1).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tvn_tenants')) {
            Schema::create('tvn_tenants', function (Blueprint $t): void {
                $t->id();
                $t->string('identifier', 64)->unique();
                $t->string('name', 190);
                $t->string('domain', 190)->nullable();
                $t->string('plan', 50)->default('free');
                $t->text('settings')->nullable();      // JSON
                $t->boolean('is_active')->default(true);
                $t->text('quota')->nullable();          // JSON
                $t->timestamps(3);
                $t->index('domain', 'idx_tenant_domain');
                $t->index('is_active', 'idx_tenant_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tvn_tenants');
    }
};

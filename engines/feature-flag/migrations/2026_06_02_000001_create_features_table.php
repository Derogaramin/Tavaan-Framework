<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جدول tvn_features — درایور database موتور FeatureFlag (DOC-075).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tvn_features')) {
            Schema::create('tvn_features', function (Blueprint $t): void {
                $t->id();
                $t->string('name', 190)->unique();
                $t->boolean('enabled')->default(true);
                $t->boolean('default_value')->default(false);
                $t->text('rules')->nullable();      // JSON: [{type,value}]
                $t->text('value')->nullable();      // JSON: مقدار غیربولی
                $t->timestamps(3);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tvn_features');
    }
};

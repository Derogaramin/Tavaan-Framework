<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * Fixture تست — T-01/T-02: ساخت جدول widgets.
 */

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widgets', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->integer('price')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};

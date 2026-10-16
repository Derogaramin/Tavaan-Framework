<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * Fixture تست — ترتیب (باید بعد از widgets اجرا شود).
 */

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_parts', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('widget_id');
            $t->string('label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_parts');
    }
};

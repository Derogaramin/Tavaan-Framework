<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Engine\Database\Migration\Migration;

/*
 * Fixture تست — T-07: مهاجرت داده‌ای idempotent + chunked (Expand-Contract).
 */

return new class extends Migration
{
    public function up(): void
    {
        // پرکردن price بر اساس qty (idempotent: فقط سطری که price=0 است)
        DB::table('widgets')
            ->where('price', 0)
            ->chunk(100, function (array $rows): void {
                foreach ($rows as $row) {
                    DB::table('widgets')
                        ->where('id', $row['id'])
                        ->update(['price' => 10]);
                }
            });
    }

    public function down(): void
    {
        DB::table('widgets')->update(['price' => 0]);
    }
};

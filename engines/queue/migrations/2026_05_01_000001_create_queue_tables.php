<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول صف — tvn_jobs / tvn_failed_jobs / tvn_job_batches (DOC-061 §5).
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tvn_jobs')) {
            Schema::create('tvn_jobs', function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->string('queue', 64);
                $t->longText('payload');
                $t->tinyInteger('attempts')->unsigned()->default(0);
                $t->unsignedBigInteger('reserved_at')->nullable();
                $t->unsignedBigInteger('available_at');
                $t->unsignedBigInteger('created_at');
                $t->index(['queue', 'available_at'], 'idx_queue_avail');
            });
        }

        if (!Schema::hasTable('tvn_failed_jobs')) {
            Schema::create('tvn_failed_jobs', function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->char('uuid', 26)->unique();
                $t->string('connection', 64)->nullable();
                $t->string('queue', 64)->nullable();
                $t->longText('payload')->nullable();
                $t->longText('exception')->nullable();
                $t->dateTime('failed_at', 3);
            });
        }

        if (!Schema::hasTable('tvn_job_batches')) {
            Schema::create('tvn_job_batches', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('name', 190)->nullable();
                $t->integer('total_jobs');
                $t->integer('pending_jobs');
                $t->integer('failed_jobs')->default(0);
                $t->longText('failed_job_ids')->nullable();
                $t->mediumText('options')->nullable();
                $t->unsignedBigInteger('cancelled_at')->nullable();
                $t->unsignedBigInteger('created_at');
                $t->unsignedBigInteger('finished_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tvn_job_batches');
        Schema::dropIfExists('tvn_failed_jobs');
        Schema::dropIfExists('tvn_jobs');
    }
};

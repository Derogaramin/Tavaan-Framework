<?php

declare(strict_types=1);

namespace Tavaan\Engine\Schema\Tests\Unit;

use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\Grammars\MySqlSchemaGrammar;
use Tavaan\Engine\Database\Schema\Grammars\PostgresSchemaGrammar;
use Tavaan\Engine\Database\Schema\Grammars\SchemaGrammar;
use Tavaan\Engine\Database\Schema\Grammars\SqliteSchemaGrammar;
use Tavaan\Testing\TestCase;

/**
 * تست compile DDL در سه درایور — DOC-048 §7 (T-01..T-05) + AC-SCH-07.
 */
final class SchemaGrammarTest extends TestCase
{
    private function blueprint(): Blueprint
    {
        $b = new Blueprint('users', creating: true);
        $b->ulid('id')->primary();
        $b->string('name', 190);
        $b->string('email')->unique();
        $b->string('password');
        $b->text('bio')->nullable();
        $b->unsignedBigInteger('role_id');
        $b->decimal('balance', 18, 4)->default(0);
        $b->enum('status', ['active', 'banned'])->default('active');
        $b->json('settings')->nullable();
        $b->boolean('is_verified')->default(false);
        $b->timestamp('email_verified_at', 3)->nullable();
        $b->timestamps(3);
        $b->softDeletes();
        $b->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        $b->index(['status', 'created_at'], 'idx_users_status');
        $b->fullText(['name', 'bio']);

        return $b;
    }

    /** @return list<string> */
    private function compile(SchemaGrammar $g, Blueprint $b): array
    {
        return $g->compileCreate($b, ['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_0900_ai_ci']);
    }

    public function testMysqlCreateDdl(): void
    {
        $statements = $this->compile(new MySqlSchemaGrammar(''), $this->blueprint());
        $create = $statements[0];

        $this->assertStringContainsString('create table `users`', $create);
        $this->assertStringContainsString('`id` char(26) not null', $create);
        $this->assertStringContainsString('primary key (`id`)', $create);
        $this->assertStringContainsString('`name` varchar(190) not null', $create);
        $this->assertStringContainsString('unique key `users_email_unique` (`email`)', $create);
        $this->assertStringContainsString('`role_id` bigint unsigned not null', $create);
        $this->assertStringContainsString('`balance` decimal(18,4) not null default 0', $create);
        $this->assertStringContainsString("`status` enum('active','banned') not null default 'active'", $create);
        $this->assertStringContainsString('`settings` json null', $create);
        $this->assertStringContainsString('`is_verified` tinyint(1) not null default 0', $create);
        $this->assertStringContainsString('`email_verified_at` timestamp(3) null', $create);
        $this->assertStringContainsString('`created_at` timestamp(3) null', $create);
        $this->assertStringContainsString('`deleted_at` timestamp null', $create);
        $this->assertStringContainsString('constraint `users_role_id_foreign` foreign key (`role_id`) references `roles` (`id`) on delete CASCADE on update RESTRICT', $create);
        $this->assertStringContainsString('key `idx_users_status` (`status`, `created_at`)', $create);
        $this->assertStringContainsString('fulltext key `users_name_bio_fulltext` (`name`, `bio`)', $create);
        $this->assertStringContainsString('engine=InnoDB default charset=utf8mb4 collate=utf8mb4_0900_ai_ci', $create);

        // index ستونی softDeletes → CREATE INDEX جدا
        $this->assertCount(2, $statements);
        $this->assertStringContainsString('create index `users_deleted_at_index`', $statements[1]);
    }

    public function testPostgresCreateDdl(): void
    {
        $statements = $this->compile(new PostgresSchemaGrammar(''), $this->blueprint());
        $create = $statements[0];

        $this->assertStringContainsString('create table "users"', $create);
        $this->assertStringContainsString('"id" char(26) not null', $create);
        $this->assertStringContainsString('"role_id" bigint not null', $create);   // بدون unsigned در PG
        $this->assertStringContainsString('"settings" jsonb null', $create);       // json → jsonb
        $this->assertStringContainsString('"is_verified" boolean not null default false', $create);
        $this->assertStringContainsString('"email_verified_at" timestamp(3) without time zone null', $create);
        $this->assertStringContainsString('check ("status" in (\'active\', \'banned\'))', $create);   // enum → check
        $this->assertStringContainsString('constraint "users_role_id_foreign" foreign key', $create);

        // unique ستونی → constraint درون جدول؛ indexها جدا
        $this->assertStringContainsString('constraint "users_email_unique" unique ("email")', $create);
        $joined = \implode(';', $statements);
        $this->assertStringContainsString('create index "idx_users_status"', $joined);
        $this->assertStringContainsString('using gin (to_tsvector', $joined);
    }

    public function testSqliteCreateDdl(): void
    {
        $statements = $this->compile(new SqliteSchemaGrammar(''), $this->blueprint());
        $create = $statements[0];

        $this->assertStringContainsString('create table "users"', $create);
        $this->assertStringContainsString('"balance" numeric not null default 0', $create);   // decimal → numeric
        $this->assertStringContainsString('check ("status" in (\'active\', \'banned\'))', $create);
        $this->assertStringContainsString('"settings" text null', $create);                   // json → text
        $this->assertStringContainsString('"is_verified" tinyint(1) not null default 0', $create);
        $this->assertStringContainsString('constraint "users_role_id_foreign" foreign key', $create);
    }

    public function testAutoIncrementPerDriver(): void
    {
        foreach ([new MySqlSchemaGrammar(''), new PostgresSchemaGrammar(''), new SqliteSchemaGrammar('')] as $g) {
            $b = new Blueprint('posts', creating: true);
            $b->id();
            $b->string('title');

            $create = $g->compileCreate($b)[0];

            match ($g->driver()) {
                'mysql' => $this->assertStringContainsString('`id` bigint unsigned not null auto_increment primary key', $create),
                'pgsql' => $this->assertStringContainsString('"id" bigserial primary key', $create),
                default => $this->assertStringContainsString('"id" integer primary key autoincrement', $create),
            };
        }
    }

    public function testAlterStatements(): void
    {
        $g = new MySqlSchemaGrammar('');
        $b = new Blueprint('users');
        $b->string('phone', 20)->after('email');
        $b->index('phone', 'idx_phone');
        $b->dropColumn('legacy');
        $b->renameColumn('old_name', 'new_name');

        $statements = $g->compileAlter($b);
        $joined = \implode(';', $statements);

        $this->assertStringContainsString('alter table `users` rename column `old_name` to `new_name`', $joined);
        $this->assertStringContainsString('alter table `users` add column `phone` varchar(20) not null after `email`', $joined);
        $this->assertStringContainsString('alter table `users` drop column `legacy`', $joined);
        $this->assertStringContainsString('alter table `users` add key `idx_phone` (`phone`)', $joined);
    }

    public function testSqliteAlterSkipsForeignKeys(): void
    {
        $g = new SqliteSchemaGrammar('');
        $b = new Blueprint('orders');
        $b->unsignedBigInteger('user_id');
        $b->foreign('user_id')->references('id')->on('users');

        $statements = $g->compileAlter($b);
        $this->assertCount(1, $statements);   // فقط add column؛ FK در sqlite alter پشتیبانی نمی‌شود
        $this->assertStringContainsString('add column "user_id"', $statements[0]);
    }

    public function testDropAndRename(): void
    {
        $g = new MySqlSchemaGrammar('tvn_');
        $this->assertSame('drop table `tvn_users`', $g->compileDrop('users'));
        $this->assertSame('drop table if exists `tvn_users`', $g->compileDropIfExists('users'));
        $this->assertSame('alter table `tvn_old` rename to `tvn_new`', $g->compileRename('old', 'new'));
    }

    public function testEnumInjectionEscaped(): void
    {
        $b = new Blueprint('t', creating: true);
        $b->enum('status', ["a', DROP TABLE x; --", 'b']);

        foreach ([new MySqlSchemaGrammar(''), new PostgresSchemaGrammar(''), new SqliteSchemaGrammar('')] as $g) {
            $create = $g->compileCreate($b)[0];
            $this->assertStringNotContainsString("a', DROP", $create, 'enum values must escape quotes for ' . $g->driver());
            $this->assertStringContainsString("''", $create);
        }
    }

    public function testFrozenBlueprintRejectsMutation(): void
    {
        $b = new Blueprint('t', creating: true);
        $col = $b->string('x');
        (new SqliteSchemaGrammar(''))->compileCreate($b);
        $b->freeze();

        $this->expectException(\LogicException::class);
        $col->nullable();
    }
}

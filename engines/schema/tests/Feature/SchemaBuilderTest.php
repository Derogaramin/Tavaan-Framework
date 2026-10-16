<?php

declare(strict_types=1);

namespace Tavaan\Engine\Schema\Tests\Feature;

use Tavaan\Engine\Config\Repository;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\SchemaBuilder;
use Tavaan\Support\Ulid;
use Tavaan\Testing\TestCase;

/**
 * تست اجرای واقعی SchemaBuilder روی SQLite — DOC-048 §7 (T-02/T-04/T-06).
 */
final class SchemaBuilderTest extends TestCase
{
    private SchemaBuilder $schema;

    private ConnectionManager $manager;

    protected function setUp(): void
    {
        $config = new Repository(['database' => [
            'default' => 'test',
            'connections' => ['test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]],
        ]]);

        $this->manager = new ConnectionManager($config);
        $this->schema = new SchemaBuilder($this->manager);
    }

    private function db(): \Tavaan\Engine\Database\Connection
    {
        return $this->manager->connection();
    }

    public function testCreateTableAndIntrospect(): void
    {
        $this->schema->create('roles', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
        });

        $this->schema->create('users', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->string('name', 190);
            $t->string('email')->unique();
            $t->unsignedBigInteger('role_id')->nullable();
            $t->decimal('balance', 18, 4)->default(0);
            $t->enum('status', ['active', 'banned'])->default('active');
            $t->json('settings')->nullable();
            $t->boolean('is_verified')->default(false);
            $t->timestamps(3);
            $t->softDeletes();
            $t->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $t->index(['status', 'created_at'], 'idx_users_status');
        });

        $this->assertTrue($this->schema->hasTable('users'));
        $this->assertFalse($this->schema->hasTable('ghosts'));
        $this->assertTrue($this->schema->hasColumn('users', 'email'));
        $this->assertFalse($this->schema->hasColumn('users', 'nope'));
        $this->assertStringContainsString('char', (string) $this->schema->getColumnType('users', 'id'));

        $tables = $this->schema->getTables();
        $this->assertContains('users', $tables);
        $this->assertContains('roles', $tables);

        $introspect = $this->schema->introspect('users');
        $this->assertSame('users', $introspect['table']);
        $this->assertGreaterThan(5, \count($introspect['columns']));
    }

    public function testInsertRespectsEnumCheck(): void
    {
        $this->schema->create('t', function (Blueprint $t): void {
            $t->enum('status', ['a', 'b'])->default('a');
        });

        $this->db()->table('t')->insert(['status' => 'a']);

        $this->expectException(\Tavaan\Engine\Database\Exceptions\QueryException::class);
        $this->db()->table('t')->insert(['status' => 'zzz']);   // check constraint → رد
    }

    public function testForeignKeyCascade(): void
    {
        $this->schema->create('parents', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
        });
        $this->schema->create('children', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('parent_id');
            $t->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade');
        });

        $pid = $this->db()->table('parents')->insertGetId(['name' => 'p1']);
        $this->db()->table('children')->insert(['parent_id' => $pid]);
        $this->assertSame(1, $this->db()->table('children')->count());

        $this->db()->table('parents')->where('id', $pid)->delete();
        $this->assertSame(0, $this->db()->table('children')->count(), 'onDelete cascade must remove children');
    }

    public function testAlterTable(): void
    {
        $this->schema->create('t', function (Blueprint $t): void {
            $t->id();
            $t->string('a');
        });

        $this->schema->table('t', function (Blueprint $t): void {
            $t->string('b', 20)->nullable();
            $t->index('b', 'idx_b');
        });

        $this->assertTrue($this->schema->hasColumn('t', 'b'));

        $this->schema->table('t', function (Blueprint $t): void {
            $t->renameColumn('a', 'a2');
        });
        $this->assertTrue($this->schema->hasColumn('t', 'a2'));
        $this->assertFalse($this->schema->hasColumn('t', 'a'));

        // 🔒 در SQLite قبل از حذف ستون، ایندکس مربوطه باید حذف شود
        $this->schema->table('t', function (Blueprint $t): void {
            $t->dropIndex('idx_b');
        });
        $this->schema->table('t', function (Blueprint $t): void {
            $t->dropColumn('b');
        });
        $this->assertFalse($this->schema->hasColumn('t', 'b'));
    }

    public function testUniqueConstraintEnforced(): void
    {
        $this->schema->create('u', function (Blueprint $t): void {
            $t->id();
            $t->string('email')->unique();
        });

        $this->db()->table('u')->insert(['email' => 'x@y.z']);

        $this->expectException(\Tavaan\Engine\Database\Exceptions\QueryException::class);
        $this->db()->table('u')->insert(['email' => 'x@y.z']);
    }

    public function testDropAndRenameAndDropAll(): void
    {
        $this->schema->create('old_t', fn (Blueprint $t) => $t->id());
        $this->schema->rename('old_t', 'new_t');
        $this->assertTrue($this->schema->hasTable('new_t'));
        $this->assertFalse($this->schema->hasTable('old_t'));

        $this->schema->drop('new_t');
        $this->assertFalse($this->schema->hasTable('new_t'));

        $this->schema->dropIfExists('ghost');   // بدون خطا

        $this->schema->create('a1', fn (Blueprint $t) => $t->id());
        $this->schema->create('a2', fn (Blueprint $t) => $t->id());
        $this->schema->dropAllTables();
        $this->assertSame([], $this->schema->getTables());
    }

    public function testUlidPrimaryKeyRoundtrip(): void
    {
        $this->schema->create('docs', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->string('title');
        });

        $ulid = Ulid::generate()->toString();
        $this->db()->table('docs')->insert(['id' => $ulid, 'title' => 'سلام']);

        $row = $this->db()->table('docs')->find($ulid);
        $this->assertNotNull($row);
        $this->assertSame('سلام', $row['title']);
        $this->assertSame(26, \strlen($ulid));
    }

    public function testTablePrefix(): void
    {
        $config = new Repository(['database' => [
            'default' => 'p',
            'connections' => ['p' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'tvn_']],
        ]]);
        $schema = new SchemaBuilder(new ConnectionManager($config));

        $schema->create('users', fn (Blueprint $t) => $t->id());
        $this->assertTrue($schema->hasTable('users'));
        $this->assertContains('users', $schema->getTables());   // prefix شفاف است
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Orm\Tests\Feature;

use Tavaan\Engine\Database\Exceptions\UnsafeQueryException;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Testing\TPost;
use Tavaan\Engine\Database\ORM\Testing\TUser;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\SchemaBuilder;
use Tavaan\Testing\TestCase;

/**
 * گارد alias در withCount — یافتهٔ ممیزی M0–M8 (security:scan):
 * alias در selectRaw correlated درون‌یابی می‌شود و binding‌پذیر نیست؛
 * باید فقط شناسهٔ [A-Za-z_][A-Za-z0-9_]* باشد وگرنه UnsafeQueryException.
 */
final class WithCountAliasGuardTest extends TestCase
{
    protected function setUp(): void
    {
        $app = \tavaan_test_app();
        $app->make('config')->set('database.default', 'sqlite_memory');
        $app->make('config')->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        Model::flushTestState();

        $app->make('db')->connection();
        /** @var SchemaBuilder $schema */
        $schema = $app->make('schema');
        $schema->create('orm_users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->unsignedInteger('tenant_id')->default(1);
            $t->string('role')->nullable();
            $t->boolean('is_admin')->default(false);
            $t->string('secret_note')->nullable();
            $t->timestamps(3);
        });
        $schema->create('orm_posts', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('title');
            $t->string('slug')->nullable();
            $t->text('body')->nullable();
            $t->string('status')->default('draft');
            $t->integer('price')->default(0);
            $t->integer('views')->default(0);
            $t->unsignedInteger('tenant_id')->default(1);
            $t->string('published_at')->nullable();
            $t->string('meta')->nullable();
            $t->timestamp('deleted_at', 3)->nullable();
            $t->timestamps(3);
        });
    }

    public function testValidAliasWorks(): void
    {
        $user = TUser::create(['name' => 'a']);
        TPost::create(['user_id' => $user->getKey(), 'title' => 'p1']);

        $users = TUser::withCount(['posts as total_posts'])->get();
        $this->assertSame(1, (int) $users[0]->total_posts);
    }

    public function testMaliciousAliasRejected(): void
    {
        TUser::create(['name' => 'a']);

        $this->expectException(UnsafeQueryException::class);
        TUser::withCount(['posts as x`) -- injection'])->get();
    }

    public function testSqlFragmentAliasRejected(): void
    {
        $this->expectException(UnsafeQueryException::class);
        TUser::withCount(['posts as a; DROP TABLE orm_users'])->get();
    }
}

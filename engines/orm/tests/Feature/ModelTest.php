<?php

declare(strict_types=1);

namespace Tavaan\Engine\Orm\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Database\Connection;
use Tavaan\Engine\Database\Exceptions\RecordNotFoundException;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Exceptions\MassAssignmentException;
use Tavaan\Engine\Database\ORM\Support\Money;
use Tavaan\Engine\Database\ORM\Testing\TenantScopeFixture;
use Tavaan\Engine\Database\ORM\Testing\TComment;
use Tavaan\Engine\Database\ORM\Testing\TMedia;
use Tavaan\Engine\Database\ORM\Testing\TPost;
use Tavaan\Engine\Database\ORM\Testing\TPostStatus;
use Tavaan\Engine\Database\ORM\Testing\TPostWasCreated;
use Tavaan\Engine\Database\ORM\Testing\TProductObserver;
use Tavaan\Engine\Database\ORM\Testing\TProfile;
use Tavaan\Engine\Database\ORM\Testing\TRole;
use Tavaan\Engine\Database\ORM\Testing\TSecureUser;
use Tavaan\Engine\Database\ORM\Testing\TTag;
use Tavaan\Engine\Database\ORM\Testing\TTenantPost;
use Tavaan\Engine\Database\ORM\Testing\TUser;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\SchemaBuilder;
use Tavaan\Testing\TestCase;

/**
 * تست ORM — DOC-050 §11 (T-01..T-14).
 */
final class ModelTest extends TestCase
{
    private Application $app;

    private Connection $db;

    protected function setUp(): void
    {
        $this->app = \tavaan_test_app();
        $this->app->make('config')->set('database.default', 'sqlite_memory');
        $this->app->make('config')->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $this->app->make('config')->set('database.query_log', ['enabled' => true, 'slow_threshold' => 100000]);
        Model::flushTestState();
        TProductObserver::$fired = [];
        TProductObserver::$blockUpdating = false;
        TenantScopeFixture::$currentTenant = 1;

        $this->db = $this->app->make('db')->connection();

        /** @var SchemaBuilder $schema */
        $schema = $this->app->make('schema');

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
        $schema->create('orm_profiles', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->text('bio')->nullable();
            $t->string('phone', 20)->nullable();
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
        $schema->create('orm_comments', function (Blueprint $t): void {
            $t->id();
            $t->string('commentable_type');
            $t->unsignedBigInteger('commentable_id');
            $t->text('body');
            $t->timestamps(3);
        });
        $schema->create('orm_medias', function (Blueprint $t): void {
            $t->id();
            $t->string('mediable_type');
            $t->unsignedBigInteger('mediable_id');
            $t->string('path');
            $t->timestamps(3);
        });
        $schema->create('orm_owners', function (Blueprint $t): void {
            $t->id();
            $t->string('ownable_type');
            $t->unsignedBigInteger('ownable_id');
            $t->string('name');
            $t->timestamps(3);
        });
        $schema->create('orm_tags', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->timestamps(3);
        });
        $schema->create('orm_post_tag', function (Blueprint $t): void {
            $t->unsignedBigInteger('post_id');
            $t->unsignedBigInteger('tag_id');
            $t->timestamps(3);
            $t->primary(['post_id', 'tag_id']);
        });
        $schema->create('orm_roles', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->timestamps(3);
        });
        $schema->create('orm_role_user', function (Blueprint $t): void {
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['user_id', 'role_id']);
        });
        $schema->create('orm_user_metas', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('nickname')->nullable();
            $t->timestamps(3);
        });
        $schema->create('orm_secure_users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->text('mobile')->nullable();
            $t->rememberToken();
            $t->timestamps(3);
        });
    }

    protected function tearDown(): void
    {
        Model::flushTestState();
    }

    // ─── T-01: CRUD کامل با fillable ──────────────────────────────────────

    public function testCrudWithFillable(): void
    {
        $user = TUser::create(['name' => 'علی', 'email' => 'ali@x.io']);
        $this->assertFalse($user->isNew());
        $this->assertNotNull($user->getKey());

        $found = TUser::find($user->getKey());
        $this->assertNotNull($found);
        $this->assertSame('علی', $found->name);

        $found->update(['name' => 'رضا']);
        $this->assertSame('رضا', (string) TUser::find($user->getKey())->name);

        $this->assertTrue($found->delete());
        $this->assertNull(TUser::find($user->getKey()));
    }

    public function testUlidKeyGeneration(): void
    {
        // keyType پیش‌فرض ulid (ADR-0006)
        $tag = TTag::create(['name' => 'x']);
        $this->assertTrue($tag->incrementing, 'TTag is bigint');

        /** @var SchemaBuilder $schema */
        $schema = $this->app->make('schema');
        $schema->create('orm_docs', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->string('title');
            $t->timestamps(3);
        });

        $ulidModel = new class extends Model {
            protected string $table = 'orm_docs';

            protected string $keyType = 'ulid';

            protected array $fillable = ['title'];
        };
        $m = $ulidModel->newInstance(['title' => 'ulid-row']);
        $m->save();
        $key = $m->getKey();
        $this->assertSame(26, \strlen((string) $key));
        $this->assertNotNull($ulidModel->newInstance()->newModelQuery()->find((string) $key));
    }

    public function testFindOrFailThrows(): void
    {
        $this->expectException(RecordNotFoundException::class);
        TUser::findOrFail(999999);
    }

    // ─── T-02: Mass Assignment ────────────────────────────────────────────

    public function testMassAssignmentGuardedRejected(): void
    {
        // is_admin در guarded است → در حالت strict باید throw شود
        $this->expectException(MassAssignmentException::class);
        TUser::create(['name' => 'x', 'is_admin' => true]);
    }

    public function testUnguardForSeeding(): void
    {
        Model::unguard();
        try {
            $user = TUser::create(['name' => 'seed', 'is_admin' => true]);
            $this->assertTrue((bool) $user->is_admin);
        } finally {
            Model::unguard(false);
        }

        // بازگشت به حالت سخت‌گیر
        $this->expectException(MassAssignmentException::class);
        TUser::create(['name' => 'x', 'is_admin' => true]);
    }

    // ─── T-03: رابطه‌ها ───────────────────────────────────────────────────

    public function testHasOneAndBelongsTo(): void
    {
        $user = TUser::create(['name' => 'a']);
        TProfile::create(['user_id' => $user->getKey(), 'bio' => 'سلام']);

        $profile = $user->profile;
        $this->assertInstanceOf(TProfile::class, $profile);
        $this->assertSame('سلام', $profile->bio);

        $this->assertSame('a', $profile->user->name);
    }

    public function testHasMany(): void
    {
        $user = TUser::create(['name' => 'a']);
        TPost::create(['user_id' => $user->getKey(), 'title' => 'p1']);
        TPost::create(['user_id' => $user->getKey(), 'title' => 'p2']);

        $this->assertCount(2, $user->posts);
    }

    public function testBelongsToManyAttachSync(): void
    {
        $post = TPost::create(['title' => 'p']);
        $t1 = TTag::create(['name' => 'php']);
        $t2 = TTag::create(['name' => 'db']);

        $relation = $post->tags();
        $relation->attach($t1->getKey());
        $relation->attach($t2->getKey());

        $fresh = TPost::find($post->getKey());
        $this->assertCount(2, $fresh->tags);

        // sync → فقط t1
        $post->tags()->sync([$t1->getKey()]);
        $fresh2 = TPost::find($post->getKey());
        $this->assertCount(1, $fresh2->tags);
        $this->assertSame('php', $fresh2->tags[0]->name);

        // سمت معکوس
        $this->assertCount(1, TTag::find($t1->getKey())->posts);
    }

    public function testHasManyThrough(): void
    {
        $user = TUser::create(['name' => 'a']);
        $meta = $this->db->table('orm_user_metas')->insertGetId(['user_id' => $user->getKey(), 'nickname' => 'al']);
        TPost::create(['user_id' => $meta, 'title' => 'through-1']);
        TPost::create(['user_id' => $meta, 'title' => 'through-2']);

        $posts = $user->distantPosts;
        $this->assertCount(2, $posts);
    }

    public function testMorphManyAndMorphTo(): void
    {
        $post = TPost::create(['title' => 'p']);
        $post->medias()->create(['path' => '/img/1.jpg']);
        $post->medias()->create(['path' => '/img/2.jpg']);

        $fresh = TPost::find($post->getKey());
        $this->assertCount(2, $fresh->medias);

        $media = $fresh->medias[0];
        $owner = $media->mediable;
        $this->assertInstanceOf(TPost::class, $owner);
        $this->assertSame('p', $owner->title);
    }

    public function testMorphOne(): void
    {
        $media = TMedia::create(['mediable_type' => TPost::class, 'mediable_id' => 1, 'path' => '/x.jpg']);
        \Tavaan\Engine\Database\ORM\Testing\TOwner::create([
            'ownable_type' => TMedia::class, 'ownable_id' => $media->getKey(), 'name' => 'owner-1',
        ]);

        $fresh = TMedia::find($media->getKey());
        $owner = $fresh->owner;
        $this->assertNotNull($owner);
        $this->assertSame('owner-1', $owner->name);
    }

    // ─── T-04: Eager loading (N+1 = ۰) ────────────────────────────────────

    public function testEagerLoadingPreventsNPlusOne(): void
    {
        $user = TUser::create(['name' => 'a']);
        for ($i = 0; $i < 20; $i++) {
            TPost::create(['user_id' => $user->getKey(), 'title' => 'p' . $i]);
        }

        $this->db->queryLogInstance()->flush();

        // Lazy: ۱ + ۲۰ کوئری
        $posts = TPost::all();
        $lazyCount = 0;
        foreach ($posts as $p) {
            $u = $p->user;
            $lazyCount++;
        }
        $lazyQueries = \count($this->db->queryLog());

        $this->db->queryLogInstance()->flush();

        // Eager: ۲ کوئری
        $posts = TPost::with('user')->get();
        foreach ($posts as $p) {
            $this->assertSame('a', $p->user->name);
        }
        $eagerQueries = \count($this->db->queryLog());

        $this->assertSame(2, $eagerQueries, 'eager loading must use exactly 2 queries');
        $this->assertTrue($lazyQueries > $eagerQueries, 'lazy must issue more queries than eager');
    }

    public function testNestedEagerLoading(): void
    {
        $user = TUser::create(['name' => 'a']);
        $post = TPost::create(['user_id' => $user->getKey(), 'title' => 'p']);
        TProfile::create(['user_id' => $user->getKey(), 'bio' => 'b']);

        $this->db->queryLogInstance()->flush();
        $posts = TPost::with('user.profile')->get();

        $this->assertSame('b', $posts[0]->user->profile->bio);
        $this->assertSame(3, \count($this->db->queryLog()), 'nested eager = 3 queries');
    }

    public function testEagerWithConstraint(): void
    {
        $user = TUser::create(['name' => 'a']);
        TPost::create(['user_id' => $user->getKey(), 'title' => 'p1', 'status' => 'published']);
        TPost::create(['user_id' => $user->getKey(), 'title' => 'p2', 'status' => 'draft']);

        $users = TUser::with(['posts' => fn ($q) => $q->where('status', 'published')])->get();
        $this->assertCount(1, $users[0]->posts);
        $this->assertSame('p1', $users[0]->posts[0]->title);
    }

    public function testWithCount(): void
    {
        $user = TUser::create(['name' => 'a']);
        TPost::create(['user_id' => $user->getKey(), 'title' => 'p1']);
        TPost::create(['user_id' => $user->getKey(), 'title' => 'p2']);

        $users = TUser::withCount('posts')->get();
        $this->assertSame(2, (int) $users[0]->posts_count);
    }

    // ─── T-05: Castها ─────────────────────────────────────────────────────

    public function testCastsMoneyEnumDatetimeJson(): void
    {
        $post = TPost::create([
            'title' => 'p',
            'price' => 15000,
            'status' => TPostStatus::Published,
            'published_at' => new \DateTimeImmutable('2026-03-21 10:00:00', new \DateTimeZone('UTC')),
        ]);

        $fresh = TPost::find($post->getKey());
        $this->assertInstanceOf(Money::class, $fresh->price);
        $this->assertSame(15000, $fresh->price->amount);
        $this->assertSame('IRR', $fresh->price->currency);
        $this->assertSame(TPostStatus::Published, $fresh->status);
        $this->assertInstanceOf(\DateTimeImmutable::class, $fresh->published_at);

        // enum از رشته
        $draft = TPost::create(['title' => 'd', 'status' => 'draft']);
        $this->assertSame(TPostStatus::Draft, TPost::find($draft->getKey())->status);

        // json/array cast
        $this->db->table('orm_posts')->where('id', $post->getKey())->update(['meta' => '{"a":1}']);
        $fresh2 = TPost::find($post->getKey());
        $this->assertSame(['a' => 1], $fresh2->meta);
    }

    public function testEncryptedAndHashedCasts(): void
    {
        $user = TSecureUser::create([
            'name' => 's',
            'email' => 's@x.io',
            'password' => 'secret-pass-1',
            'mobile' => '09121234567',
        ]);

        // در DB: password هش و mobile رمزنگاری‌شده است
        $raw = $this->db->table('orm_secure_users')->find($user->getKey());
        $this->assertNotSame('secret-pass-1', $raw['password']);
        $this->assertTrue(\password_verify('secret-pass-1', (string) $raw['password']));
        $this->assertStringNotContainsString('09121234567', (string) $raw['mobile']);

        // خواندن از مدل: mobile رمزگشایی می‌شود
        $fresh = TSecureUser::find($user->getKey());
        $this->assertSame('09121234567', $fresh->mobile);
    }

    // ─── T-06: Accessor/Mutator ───────────────────────────────────────────

    public function testAccessorAndMutator(): void
    {
        $post = TPost::create(['title' => 'Hello World', 'slug' => 'Hello World', 'body' => \str_repeat('الف ', 30)]);

        // mutator: slug خودکار نرمال می‌شود
        $this->assertSame('hello-world', $post->slug);

        // accessor + appends: excerpt در toArray
        $array = $post->toArray();
        $this->assertArrayHasKey('excerpt', $array);
        $this->assertTrue(\mb_strlen((string) $array['excerpt']) <= 31);
    }

    // ─── T-07: Soft Delete ────────────────────────────────────────────────

    public function testSoftDelete(): void
    {
        $post = TPost::create(['title' => 'p']);

        $post->delete();
        $this->assertNull(TPost::find($post->getKey()));        // دامنهٔ پیش‌فرض
        $this->assertSame(0, TPost::count());

        $trashed = TPost::withTrashed()->find($post->getKey());
        $this->assertNotNull($trashed);
        $this->assertTrue($trashed->trashed());

        $only = TPost::onlyTrashed()->get();
        $this->assertCount(1, $only);

        $trashed->restore();
        $this->assertNotNull(TPost::find($post->getKey()));

        $trashed->forceDelete();
        $this->assertSame(0, TPost::withTrashed()->count());
    }

    // ─── T-08: Global Scope ───────────────────────────────────────────────

    public function testGlobalScopeTenant(): void
    {
        TPost::create(['title' => 't1', 'tenant_id' => 1]);
        TPost::create(['title' => 't2', 'tenant_id' => 2]);

        TenantScopeFixture::$currentTenant = 1;
        $this->assertSame(1, TTenantPost::count());
        $this->assertSame('t1', TTenantPost::first()->title);

        TenantScopeFixture::$currentTenant = 2;
        $this->assertSame(1, TTenantPost::count());
        $this->assertSame('t2', TTenantPost::first()->title);
    }

    // ─── T-09: Observer ───────────────────────────────────────────────────

    public function testObserverEvents(): void
    {
        TPost::observe(TProductObserver::class);

        $post = TPost::create(['title' => 'p']);
        foreach (['saving', 'creating', 'created', 'saved'] as $expected) {
            $this->assertContains($expected, TProductObserver::$fired);
        }

        TProductObserver::$fired = [];
        $post->title = 'p2';
        $post->save();
        $this->assertContains('updating', TProductObserver::$fired);
        $this->assertContains('updated', TProductObserver::$fired);

        // updating → false = لغو
        TProductObserver::$blockUpdating = true;
        TProductObserver::$fired = [];
        $post->title = 'p3';
        $result = $post->save();
        $this->assertFalse($result);
        $this->assertContains('updating', TProductObserver::$fired);
        $this->assertTrue(!\in_array('updated', TProductObserver::$fired, true));

        TProductObserver::$blockUpdating = false;
        TProductObserver::$fired = [];
        $post->delete();
        $this->assertContains('deleting', TProductObserver::$fired);
        $this->assertContains('deleted', TProductObserver::$fired);
    }

    // ─── T-10: dispatchesEvents ───────────────────────────────────────────

    public function testDispatchesEventsConvertsToDomainEvent(): void
    {
        $captured = [];
        $dispatcher = $this->app->make(DispatcherInterface::class);
        $dispatcher->listen(TPostWasCreated::class, function (TPostWasCreated $event) use (&$captured): void {
            $captured[] = $event;
        });

        $model = new class extends TPost {
            protected array $dispatchesEvents = ['created' => TPostWasCreated::class];
        };
        $instance = $model->newInstance(['title' => 'domain-event']);
        $instance->save();

        $this->assertCount(1, $captured);
        $this->assertSame('domain-event', $captured[0]->post->title);
    }

    // ─── T-12: hidden در toJson ───────────────────────────────────────────

    public function testHiddenFieldsNotSerialized(): void
    {
        Model::unguard();
        try {
            $user = TUser::create(['name' => 'a', 'secret_note' => 'top-secret']);
        } finally {
            Model::unguard(false);
        }

        $json = $user->toJson();
        $this->assertStringNotContainsString('top-secret', $json);
        $this->assertStringContainsString('name', $json);

        $array = $user->toArray();
        $this->assertArrayNotHasKey('secret_note', $array);
    }

    // ─── T-13: updateOrCreate / firstOrCreate ─────────────────────────────

    public function testFirstOrCreateAndUpdateOrCreate(): void
    {
        $u1 = TUser::firstOrCreate(['email' => 'x@y.z'], ['name' => 'first']);
        $u2 = TUser::firstOrCreate(['email' => 'x@y.z'], ['name' => 'second']);
        $this->assertSame($u1->getKey(), $u2->getKey());
        $this->assertSame('first', $u2->name);

        $u3 = TUser::updateOrCreate(['email' => 'x@y.z'], ['name' => 'updated']);
        $this->assertSame($u1->getKey(), $u3->getKey());
        $this->assertSame('updated', $u3->name);
        $this->assertSame(1, TUser::count());
    }

    // ─── dirty tracking ───────────────────────────────────────────────────

    public function testDirtyTracking(): void
    {
        $user = TUser::create(['name' => 'a']);
        $this->assertFalse($user->isDirty());

        $user->name = 'b';
        $this->assertTrue($user->isDirty());
        $this->assertTrue($user->isDirty('name'));
        $this->assertArrayHasKey('name', $user->getChanges());
        $this->assertSame('a', $user->getOriginal('name')['name']);

        $user->save();
        $this->assertFalse($user->isDirty());
    }

    public function testReplicateAndRefresh(): void
    {
        $user = TUser::create(['name' => 'a', 'email' => 'a@x.io']);
        $copy = $user->replicate();
        $this->assertTrue($copy->isNew());
        $this->assertSame('a', $copy->name);

        $user->refresh();
        $this->assertSame('a', $user->name);
    }

    // ─── scope محلی + query builder ───────────────────────────────────────

    public function testLocalScopes(): void
    {
        TPost::create(['title' => 'p1', 'status' => 'published']);
        TPost::create(['title' => 'p2', 'status' => 'draft']);

        $published = TPost::published()->get();
        $this->assertCount(1, $published);
        $this->assertSame('p1', $published[0]->title);

        $tenant = TPost::ofTenant(1)->published()->orderByDesc('views')->get();
        $this->assertCount(1, $tenant);
    }

    public function testChunkAndPaginate(): void
    {
        for ($i = 0; $i < 25; $i++) {
            TPost::create(['title' => 'p' . $i]);
        }

        $seen = 0;
        TPost::chunk(10, function (array $models) use (&$seen): void {
            $seen += \count($models);
        });
        $this->assertSame(25, $seen);

        $paginator = TPost::orderBy('id')->paginate(perPage: 10, page: 2);
        $this->assertSame(25, $paginator->total());
        $this->assertSame(3, $paginator->lastPage());
        $this->assertInstanceOf(TPost::class, $paginator->items()[0]);
    }

    // ─── T-14: Hydration کارایی ───────────────────────────────────────────

    public function testHydration1000Records(): void
    {
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = ['title' => 'p' . $i, 'status' => 'published', 'price' => $i * 10, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
        }
        foreach (\array_chunk($rows, 100) as $chunk) {
            $this->db->table('orm_posts')->insert($chunk);
        }

        $start = \microtime(true);
        $models = TPost::all();
        $elapsedMs = (\microtime(true) - $start) * 1000;

        $this->assertCount(1000, $models);
        $this->assertInstanceOf(TPost::class, $models[0]);
        $this->assertInstanceOf(Money::class, $models[0]->price);
        // هدف DOC-050: ≤ ۲۵ms — سقف تست CI برای پایداری: ۲۵۰ms (بنچمارک دقیق: engines/orm/benchmarks)
        $this->assertLessThan(250, $elapsedMs, 'hydration took ' . \round($elapsedMs, 1) . 'ms');
    }
}

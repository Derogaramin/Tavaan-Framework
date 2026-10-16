<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session\Tests\Unit;

use Tavaan\Engine\Config\Repository;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\SchemaBuilder;
use Tavaan\Engine\Session\Handlers\DatabaseHandler;
use Tavaan\Testing\TestCase;

/**
 * تست درایور database نشست — تکمیل TVN-0022 (DOC-041).
 */
final class DatabaseSessionHandlerTest extends TestCase
{
    private DatabaseHandler $handler;

    protected function setUp(): void
    {
        $config = new Repository(['database' => [
            'default' => 'test',
            'connections' => ['test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']],
        ]]);
        $manager = new ConnectionManager($config);

        (new SchemaBuilder($manager))->create('sessions', function (Blueprint $t): void {
            $t->string('id', 64)->primary();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->text('payload');
            $t->unsignedBigInteger('last_activity')->index();
        });

        $this->handler = new DatabaseHandler($manager->connection());
    }

    public function testWriteReadRoundtrip(): void
    {
        $data = ['user_id' => 42, 'locale' => 'fa', 'flash' => ['سلام' => 'دنیا']];
        $this->assertTrue($this->handler->write('sess-1', $data));

        $read = $this->handler->read('sess-1');
        $this->assertSame(42, $read['user_id']);
        $this->assertSame('دنیا', $read['flash']['سلام']);
    }

    public function testReadMissingReturnsEmpty(): void
    {
        $this->assertSame([], $this->handler->read('ghost'));
    }

    public function testWriteIsUpsert(): void
    {
        $this->handler->write('sess-2', ['a' => 1]);
        $this->handler->write('sess-2', ['a' => 2, 'b' => 3]);

        $read = $this->handler->read('sess-2');
        $this->assertSame(2, $read['a']);
        $this->assertSame(3, $read['b']);
    }

    public function testDestroy(): void
    {
        $this->handler->write('sess-3', ['x' => 1]);
        $this->assertTrue($this->handler->destroy('sess-3'));
        $this->assertSame([], $this->handler->read('sess-3'));
    }

    public function testGarbageCollection(): void
    {
        $this->handler->write('sess-old', ['x' => 1]);
        $this->handler->write('sess-new', ['x' => 2]);

        // last_activity دستی برای رکورد قدیمی
        \Tavaan\Support\Time\Clock::setTestNow(new \DateTimeImmutable('-2 hours', new \DateTimeZone('UTC')));
        $this->handler->write('sess-old', ['x' => 1]);
        \Tavaan\Support\Time\Clock::setTestNow(null);

        $removed = $this->handler->gc(3600);   // ۱ ساعت
        $this->assertSame(1, $removed);
        $this->assertSame([], $this->handler->read('sess-old'));
        $this->assertSame(2, $this->handler->read('sess-new')['x']);
    }

    public function testNoPhpSerializationInPayload(): void
    {
        // 🔒 payload فقط JSON است (Object Injection ممنوع)
        $this->handler->write('sess-4', ['danger' => 'O:8:"stdClass":0:{}']);
        $read = $this->handler->read('sess-4');
        $this->assertSame('O:8:"stdClass":0:{}', $read['danger']);   // به‌عنوان رشتهٔ ساده
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session\Tests\Unit;

use Tavaan\Engine\Crypt\Crypt;
use Tavaan\Engine\Crypt\KeyRing;
use Tavaan\Engine\Session\Handlers\ArrayHandler;
use Tavaan\Engine\Session\Handlers\CookieHandler;
use Tavaan\Engine\Session\Handlers\FileHandler;
use Tavaan\Engine\Session\Session;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Session — سناریوهای DOC-041 §9 (AC-SES).
 */
final class SessionTest extends TestCase
{
    private ArrayHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ArrayHandler();
    }

    private function session(string $id = '', ?object $handler = null): Session
    {
        return new Session(
            id: $id !== '' ? $id : Session::generateId(),
            name: 'tavaan_session',
            handler: $handler ?? $this->handler,
        );
    }

    /** T-01: start/get/put/has/flush */
    public function testBasicOperations(): void
    {
        $s = $this->session();
        $s->start();

        $s->put('user', 'amin');
        $this->assertTrue($s->has('user'));
        $this->assertSame('amin', $s->get('user'));
        $this->assertTrue($s->exists('user'));

        $s->put('nested.key', 42);
        $this->assertSame(42, $s->get('nested.key'));

        $this->assertSame('amin', $s->pull('user'));
        $this->assertFalse($s->has('user'));

        $s->flush();
        $this->assertSame([], $s->all());
    }

    public function testPushAndIncrement(): void
    {
        $s = $this->session();
        $s->start();
        $s->push('cart', 'a');
        $s->push('cart', 'b');
        $this->assertSame(['a', 'b'], $s->get('cart'));
        $this->assertSame(1, $s->increment('visits'));
        $this->assertSame(2, $s->increment('visits'));
    }

    /** T-06: flash فقط یک درخواست */
    public function testFlashLifecycle(): void
    {
        $id = Session::generateId();
        $s1 = $this->session($id);
        $s1->start();
        $s1->flash('status', 'ok');
        $s1->save();

        // درخواست دوم: flash در دسترس
        $s2 = $this->session($id);
        $s2->start();
        $this->assertSame('ok', $s2->get('status'));
        $s2->save();

        // درخواست سوم: رفته
        $s3 = $this->session($id);
        $s3->start();
        $this->assertNull($s3->get('status'));
    }

    public function testReflashAndKeep(): void
    {
        $id = Session::generateId();
        $s1 = $this->session($id);
        $s1->start();
        $s1->flash('msg', 'hi');
        $s1->save();

        $s2 = $this->session($id);
        $s2->start();
        $s2->reflash();
        $s2->save();

        $s3 = $this->session($id);
        $s3->start();
        $this->assertSame('hi', $s3->get('msg'));
    }

    public function testOldInput(): void
    {
        $s = $this->session();
        $s->start();
        $s->flashInput(['email' => 'a@b.c']);
        $this->assertSame('a@b.c', $s->getOldInput('email'));
        $this->assertTrue($s->hasOldInput('email'));
        $this->assertNull($s->getOldInput('nope'));
    }

    /** T-02: regenerate */
    public function testRegenerateKeepsDataChangesId(): void
    {
        $s = $this->session();
        $s->start();
        $s->put('user', 'amin');
        $oldId = $s->id();

        $newId = $s->regenerate(destroy: true);
        $this->assertNotSame($oldId, $newId);
        $this->assertSame('amin', $s->get('user'));
        $this->assertTrue(Session::isValidId($newId));
    }

    /** T-08: ID نامعتبر */
    public function testInvalidIdRejected(): void
    {
        $this->assertFalse(Session::isValidId('../evil'));
        $this->assertFalse(Session::isValidId('SHORT'));
        $this->assertFalse(Session::isValidId(\str_repeat('z', 40)));
        $this->assertTrue(Session::isValidId(\str_repeat('ab', 20)));
        $this->assertSame(40, \strlen(Session::generateId()));
    }

    /** T-03: idle timeout → flush */
    public function testIdleTimeoutFlushes(): void
    {
        $id = Session::generateId();
        $s1 = new Session($id, 'tvn', $this->handler, idleTimeout: 1);
        $s1->start();
        $s1->put('data', 'x');
        $s1->save();

        // دستکاری last_activity برای شبیه‌سازی گذر زمان
        $raw = $this->handler->read($id);
        $raw['_last_activity'] = \time() - 100;
        $this->handler->write($id, $raw);

        $s2 = new Session($id, 'tvn', $this->handler, idleTimeout: 1);
        $s2->start();
        $this->assertNull($s2->get('data'), 'idle-expired session must be flushed');
    }

    /** T-04: fingerprint تغییر → invalidate */
    public function testFingerprintChangeInvalidates(): void
    {
        $id = Session::generateId();
        $s1 = new Session($id, 'tvn', $this->handler, fingerprintEnabled: true, fingerprint: 'fp-A');
        $s1->start();
        $s1->put('auth', 'yes');
        $s1->save();

        $s2 = new Session($id, 'tvn', $this->handler, fingerprintEnabled: true, fingerprint: 'fp-B');
        $s2->start();
        $this->assertNull($s2->get('auth'), 'fingerprint change must invalidate');
    }

    /** CSRF token */
    public function testCsrfToken(): void
    {
        $s = $this->session();
        $s->start();
        $t1 = $s->token();
        $this->assertSame(40, \strlen($t1));
        $this->assertSame($t1, $s->token(), 'token is stable within session');
        $this->assertNotSame($t1, $s->regenerateToken());
    }

    /** T-07: serialization فقط JSON — object injection ممکن نیست */
    public function testSerializationIsJsonOnly(): void
    {
        $dir = \sys_get_temp_dir() . '/tvn-sess-' . \getmypid();
        @\mkdir($dir, 0775, true);
        $file = new FileHandler($dir);

        $id = Session::generateId();
        $s = new Session($id, 'tvn', $file);
        $s->start();
        $s->put('x', ['a' => 1]);
        $s->save();

        $raw = (string) \file_get_contents($dir . '/sess_' . $id);
        $this->assertStringContainsString('{"', $raw, 'storage format must be JSON');

        // payload مخرب serialize() → به‌عنوان JSON بی‌ضرر است
        \file_put_contents($dir . '/sess_' . $id, 'O:8:"stdClass":0:{}');
        $s2 = new Session($id, 'tvn', $file);
        $s2->start();
        // دادهٔ قبلی بازیابی نشد (فقط کلیدهای داخلی start)
        $this->assertNull($s2->get('k'));
        $this->assertFalse($s2->exists('k'));

        @\unlink($dir . '/sess_' . $id);
        @\rmdir($dir);
    }

    /** file handler roundtrip + GC (T-09/T-10) */
    public function testFileHandlerRoundtripAndGc(): void
    {
        $dir = \sys_get_temp_dir() . '/tvn-sess2-' . \getmypid();
        @\mkdir($dir, 0775, true);
        $file = new FileHandler($dir, lottery: [0, 100]);   // بدون GC تصادفی

        $id = Session::generateId();
        $this->assertTrue($file->write($id, ['k' => 'v']));
        $this->assertSame(['k' => 'v'], $file->read($id));

        // فایل قدیمی → GC حذف کند
        \touch($dir . '/sess_' . \str_repeat('a', 40), \time() - 999999);
        $removed = $file->gc(3600);
        $this->assertGreaterThan(0, $removed);

        $this->assertTrue($file->destroy($id));
        $this->assertSame([], $file->read($id));

        @\unlink($dir . '/sess_' . $id);
        @\rmdir($dir);
    }

    /** file handler: path traversal در id → reject */
    public function testFileHandlerRejectsTraversalId(): void
    {
        $dir = \sys_get_temp_dir() . '/tvn-sess3-' . \getmypid();
        @\mkdir($dir, 0775, true);
        $file = new FileHandler($dir);

        $this->expectException(\InvalidArgumentException::class);
        $file->read('../../etc/passwd');
    }

    /** cookie driver (T-09 subset) با crypt */
    public function testCookieDriverEncrypted(): void
    {
        $crypt = new Crypt(new KeyRing(KeyRing::generate()));
        $handler = new CookieHandler($crypt);

        $id = Session::generateId();
        $handler->write($id, ['user' => 'امین', 'n' => 1]);
        $encrypted = (string) $handler->cookieValue($id);
        $this->assertNotSame('', $encrypted);
        $this->assertStringNotContainsString('امین', $encrypted);

        $fresh = new CookieHandler($crypt);
        $fresh->seedFromCookie($id, $encrypted);
        $this->assertSame(['user' => 'امین', 'n' => 1], $fresh->read($id));

        // tamper → خالی
        $tampered = new CookieHandler($crypt);
        $tampered->seedFromCookie($id, $encrypted . 'x');
        $this->assertSame([], $tampered->read($id));
    }

    /** previousUrl */
    public function testPreviousUrl(): void
    {
        $s = $this->session();
        $s->start();
        $this->assertNull($s->previousUrl());
        $s->setPreviousUrl('/last');
        $this->assertSame('/last', $s->previousUrl());
    }
}

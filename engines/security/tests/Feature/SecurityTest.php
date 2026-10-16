<?php

declare(strict_types=1);

namespace Tavaan\Engine\Security\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Exceptions\Http\CsrfTokenMismatchException;
use Tavaan\Core\Exceptions\Http\TooManyRequestsException;
use Tavaan\Engine\Cache\CacheRepository;
use Tavaan\Engine\Cache\Stores\ArrayStore;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Security\Csrf\CsrfManager;
use Tavaan\Engine\Security\Middleware\CsrfVerifyMiddleware;
use Tavaan\Engine\Security\Middleware\SecurityHeadersMiddleware;
use Tavaan\Engine\Security\Middleware\ThrottleMiddleware;
use Tavaan\Engine\Security\Normalize\InputNormalizer;
use Tavaan\Engine\Security\RateLimit\RateLimiter;
use Tavaan\Engine\Security\Sanitize\HtmlSanitizer;
use Tavaan\Engine\Security\Security;
use Tavaan\Engine\Security\Ssrf\SsrfGuard;
use Tavaan\Engine\Session\Handlers\ArrayHandler;
use Tavaan\Engine\Session\Session;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Security — DOC-059 §5 (T-01..T-10).
 */
final class SecurityTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        Clock::setTestNow(null);
        $this->app = \tavaan_test_app();
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
    }

    // ─── T-01/T-02: CSRF ──────────────────────────────────────────────────

    public function testCsrfMissingToken419(): void
    {
        $session = new Session(id: 'csrf-test', name: 'tvn', handler: new ArrayHandler());
        $session->start();

        $csrf = new CsrfManager(static fn (): ?Session => $session);
        $middleware = new CsrfVerifyMiddleware($csrf, ['enabled' => true, 'except' => []]);

        $request = Request::create('/form', 'POST', ['name' => 'x']);
        $request = $request->withAttribute('session', $session);

        $next = static fn (Request $r): Response => new Response('ok');

        try {
            $middleware->handle($request, $next);
            self::fail('missing token must throw 419');
        } catch (CsrfTokenMismatchException $e) {
            $this->assertSame(419, $e->statusCode);
        }

        // توکن درست → پاس
        $ok = Request::create('/form', 'POST', ['name' => 'x', '_token' => $session->token()]);
        $ok = $ok->withAttribute('session', $session);
        $response = $middleware->handle($ok, $next);
        $this->assertSame(200, $response->statusCode());
    }

    public function testCsrfSafeMethodsExempt(): void
    {
        $csrf = new CsrfManager(static fn (): ?Session => null);
        $middleware = new CsrfVerifyMiddleware($csrf, ['enabled' => true]);

        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $request = Request::create('/x', $method);
            $response = $middleware->handle($request, static fn (Request $r): Response => new Response('ok'));
            $this->assertSame(200, $response->statusCode());
        }
    }

    public function testCsrfExceptPaths(): void
    {
        $csrf = new CsrfManager(static fn (): ?Session => null);
        $middleware = new CsrfVerifyMiddleware($csrf, ['enabled' => true, 'except' => ['webhook/*', '/api/notify']]);

        $request = Request::create('/webhook/stripe', 'POST');
        $response = $middleware->handle($request, static fn (Request $r): Response => new Response('ok'));
        $this->assertSame(200, $response->statusCode());
    }

    public function testCsrfTokenFieldRotateVerify(): void
    {
        $session = new Session(id: 'csrf-api', name: 'tvn', handler: new ArrayHandler());
        $session->start();

        $csrf = new CsrfManager(static fn (): ?Session => $session);

        $token = $csrf->token();
        $this->assertSame(40, \strlen($token));
        $this->assertStringContainsString($token, $csrf->field());
        $this->assertTrue($csrf->verify($token));
        $this->assertFalse($csrf->verify($token . 'x'));
        $this->assertFalse($csrf->verify(''));

        $new = $csrf->rotate();
        $this->assertNotSame($token, $new);
        $this->assertFalse($csrf->verify($token));
        $this->assertTrue($csrf->verify($new));
    }

    public function testCsrfDoubleSubmit(): void
    {
        $crypt = $this->app->make('encrypter');
        $csrf = new CsrfManager(static fn (): ?Session => null, $crypt);

        $token = \bin2hex(\random_bytes(20));
        $cookie = $csrf->doubleSubmitCookieValue($token);

        $this->assertNotSame($token, $cookie);
        $this->assertTrue($csrf->verifyDoubleSubmit($cookie, $token));
        $this->assertFalse($csrf->verifyDoubleSubmit($cookie, 'wrong'));
        $this->assertFalse($csrf->verifyDoubleSubmit('tampered' . $cookie, $token));
    }

    // ─── T-03/T-04: Rate Limit ────────────────────────────────────────────

    public function testRateLimit429WithRetryAfter(): void
    {
        $limiter = new RateLimiter(new CacheRepository(new ArrayStore()), 'sliding');
        $middleware = new ThrottleMiddleware($limiter);

        $next = static fn (Request $r): Response => new Response('ok');

        for ($i = 1; $i <= 60; $i++) {
            $request = Request::create('/api/x', 'GET', server: ['REMOTE_ADDR' => '203.0.113.5']);
            $response = $middleware->handle($request, $next);
            $this->assertSame('60', $response->header('X-RateLimit-Limit'));
            $this->assertSame((string) (60 - $i), $response->header('X-RateLimit-Remaining'));
        }

        // درخواست ۶۱ → ۴۲۹
        $request = Request::create('/api/x', 'GET', server: ['REMOTE_ADDR' => '203.0.113.5']);
        try {
            $middleware->handle($request, $next);
            self::fail('61st request must be 429');
        } catch (TooManyRequestsException $e) {
            $this->assertSame(429, $e->statusCode);
            $this->assertTrue(isset($e->headers['Retry-After']));
            $this->assertSame('0', $e->headers['X-RateLimit-Remaining']);
        }
    }

    public function testSlidingWindowAccuracy(): void
    {
        $limiter = new RateLimiter(new CacheRepository(new ArrayStore()), 'sliding');

        // ۳ درخواست در پنجرهٔ ۱۰ ثانیه
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($limiter->attempt('k', 3, 10));
        }
        $this->assertFalse($limiter->attempt('k', 3, 10));

        // ۶ ثانیه بعد → هنوز قفل (sliding: همهٔ ۳ ضربه در ۱۰ ثانیهٔ اخیر)
        Clock::setTestNow(new \DateTimeImmutable('+6 seconds'));
        $this->assertFalse($limiter->attempt('k', 3, 10));

        // ۱۱ ثانیه پس از اولین ضربه → اولین ضربه از پنجره خارج → آزاد
        Clock::setTestNow(new \DateTimeImmutable('+11 seconds'));
        $this->assertTrue($limiter->attempt('k', 3, 10));
    }

    public function testFixedWindowAndTokenBucket(): void
    {
        $fixed = new RateLimiter(new CacheRepository(new ArrayStore()), 'fixed');
        $this->assertTrue($fixed->attempt('f', 2, 60));
        $this->assertTrue($fixed->attempt('f', 2, 60));
        $this->assertFalse($fixed->attempt('f', 2, 60));
        Clock::setTestNow(new \DateTimeImmutable('+61 seconds'));
        $this->assertTrue($fixed->attempt('f', 2, 60));
        Clock::setTestNow(null);

        $bucket = new RateLimiter(new CacheRepository(new ArrayStore()), 'token_bucket');
        $bucket->ensureBucket('b', 3);
        $this->assertTrue($bucket->attempt('b', 3, 30));
        $this->assertTrue($bucket->attempt('b', 3, 30));
        $this->assertTrue($bucket->attempt('b', 3, 30));
        $this->assertFalse($bucket->attempt('b', 3, 30));

        // پس از گذشت زمان → توکن پر می‌شود
        Clock::setTestNow(new \DateTimeImmutable('+20 seconds'));
        $this->assertTrue($bucket->attempt('b', 3, 30));
        Clock::setTestNow(null);
    }

    public function testRateLimiterApi(): void
    {
        $limiter = new RateLimiter(new CacheRepository(new ArrayStore()), 'sliding');

        $this->assertSame(0, $limiter->attempts('api-key'));
        $this->assertSame(5, $limiter->remaining('api-key', 5, 60));

        $limiter->hit('api-key', 60);
        $this->assertSame(1, $limiter->attempts('api-key', 60));
        $this->assertSame(4, $limiter->remaining('api-key', 5, 60));
        $this->assertTrue($limiter->tooManyAttempts('api-key', 1, 60));

        $limiter->clear('api-key');
        $this->assertSame(0, $limiter->attempts('api-key', 60));
    }

    // ─── T-05: Sanitizer ──────────────────────────────────────────────────

    public function testSanitizerRemovesXssVectors(): void
    {
        $sanitizer = new HtmlSanitizer([
            'allowed_tags' => ['p', 'b', 'a', 'img'],
            'allowed_attributes' => ['a' => ['href', 'title'], 'img' => ['src', 'alt']],
            'allowed_protocols' => ['http', 'https', 'mailto'],
            'strip_comments' => true,
            'remove_scripts' => true,
        ]);

        // onerror حذف
        $clean = $sanitizer->sanitize('<img src="x.png" onerror="alert(1)" alt="a">');
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('src="x.png"', $clean);

        // javascript: URI حذف
        $clean = $sanitizer->sanitize('<a href="javascript:alert(document.cookie)">click</a>');
        $this->assertStringNotContainsString('javascript', $clean);
        $this->assertStringContainsString('click', $clean);   // محتوا حفظ

        // entity-encoded javascript
        $clean = $sanitizer->sanitize('<a href="java&#115;cript:alert(1)">x</a>');
        $this->assertStringNotContainsString('cript:', $clean);

        // script با محتوا حذف
        $clean = $sanitizer->sanitize('<p>سلام</p><script>alert(1)</script><p>بعد</p>');
        $this->assertStringNotContainsString('alert', $clean);
        $this->assertStringContainsString('<p>سلام</p>', $clean);
        $this->assertStringContainsString('<p>بعد</p>', $clean);

        // iframe حذف
        $clean = $sanitizer->sanitize('<iframe src="http://evil.com"></iframe><b>bold</b>');
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringContainsString('<b>bold</b>', $clean);

        // tag غیرمجاز → حذف تگ، حفظ محتوا
        $clean = $sanitizer->sanitize('<div><marquee>text</marquee></div>');
        $this->assertStringNotContainsString('<div>', $clean);
        $this->assertStringContainsString('text', $clean);

        // comment حذف
        $clean = $sanitizer->sanitize('<p>x</p><!-- secret -->');
        $this->assertStringNotContainsString('secret', $clean);

        // attribute غیرمجاز (style) حذف
        $clean = $sanitizer->sanitize('<p style="background:url(javascript:alert(1))">x</p>');
        $this->assertStringNotContainsString('style', $clean);

        // متن ساده سالم می‌ماند
        $clean = $sanitizer->sanitize('<p>متن <b>فارسی</b> با <a href="https://x.io" title="t">لینک</a></p>');
        $this->assertStringContainsString('<a href="https://x.io" title="t">لینک</a>', $clean);

        // stray < escape
        $clean = $sanitizer->sanitize('a < b and 5<6');
        $this->assertStringNotContainsString('<', $clean);
        $this->assertStringContainsString('&lt;', $clean);
    }

    public function testStrictEscapesAll(): void
    {
        $strict = new HtmlSanitizer(['escape_all' => true]);
        $clean = $strict->sanitize('<b>bold</b> & "quotes"');
        $this->assertStringNotContainsString('<b>', $clean);
        $this->assertStringContainsString('&lt;b&gt;', $clean);
        $this->assertStringContainsString('&amp;', $clean);
    }

    // ─── T-06/T-07: SSRF ──────────────────────────────────────────────────

    public function testSsrfBlocksPrivateRanges(): void
    {
        $guard = new SsrfGuard([], fn (string $host): array => ['93.184.216.34']);

        foreach ([
            'http://127.0.0.1/x',
            'http://localhost/admin',
            'http://10.0.0.5/',
            'http://172.16.0.1/',
            'http://192.168.1.1/',
            'http://169.254.169.254/latest/meta-data/',   // 🔒 cloud metadata
            'http://[::1]/',
            'http://100.64.0.1/',
            'file:///etc/passwd',
            'gopher://127.0.0.1:6379/_FLUSHALL',
            'dict://x',
        ] as $url) {
            $this->assertFalse($guard->isAllowed($url), $url . ' must be blocked');
        }

        // IP عمومی مجاز
        $this->assertTrue($guard->isAllowed('https://93.184.216.34/x'));
    }

    public function testSsrfDnsRebindingBlocked(): void
    {
        // T-07: دامنه‌ای که به IP خصوصی resolve می‌شود
        $guard = new SsrfGuard([], fn (string $host): array => $host === 'rebind.attacker.com' ? ['127.0.0.1'] : ['93.184.216.34']);

        $this->assertFalse($guard->isAllowed('http://rebind.attacker.com/admin'));
        $this->assertTrue($guard->isAllowed('http://example.com/'));

        // چند IP: اگر حتی یکی خصوصی باشد → رد
        $mixed = new SsrfGuard([], fn (string $host): array => ['93.184.216.34', '10.0.0.1']);
        $this->assertFalse($mixed->isAllowed('http://mixed.example.com/'));
    }

    public function testSsrfUserinfoAndDnsPinning(): void
    {
        $guard = new SsrfGuard([], fn (string $host): array => ['93.184.216.34']);

        // userinfo trick
        $this->assertFalse($guard->isAllowed('http://example.com@127.0.0.1/'));

        // DNS pinning → IP برگردان
        $pinned = $guard->resolvePinned('http://example.com/x');
        $this->assertSame(['93.184.216.34'], $pinned);

        // allowlist
        $allow = new SsrfGuard(['allowed_hosts' => ['*.internal.co']], fn (string $h): array => ['10.0.0.9']);
        $this->assertTrue($allow->isAllowed('http://hooks.internal.co/wh'));
    }

    // ─── T-08: Security Headers ───────────────────────────────────────────

    public function testSecurityHeadersApplied(): void
    {
        $middleware = new SecurityHeadersMiddleware($this->app->make('config'));

        $request = Request::create('/x', 'GET');
        $response = $middleware->handle($request, static fn (Request $r): Response => new Response('ok'));

        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertNotNull($response->header('Content-Security-Policy'));
        $this->assertSame('same-origin', $response->header('Cross-Origin-Opener-Policy'));
        $this->assertSame('strict-origin-when-cross-origin', $response->header('Referrer-Policy'));

        // X-Powered-By حذف
        $leaky = $middleware->handle(
            $request,
            static fn (Request $r): Response => (new Response('ok'))->withHeader('X-Powered-By', 'PHP'),
        );
        $this->assertNull($leaky->header('X-Powered-By'));
    }

    // ─── T-09: Signature ──────────────────────────────────────────────────

    public function testSignatureWithExpiry(): void
    {
        $security = $this->app->make('security');
        $this->assertInstanceOf(Security::class, $security);

        $signed = $security->sign('https://example.com/unsubscribe?user=1', new \DateTimeImmutable('+7 days'));
        $this->assertTrue($security->validateSignature($signed));

        // دست‌کاری → رد
        $this->assertFalse($security->validateSignature($signed . 'x'));
        $this->assertFalse($security->validateSignature(\str_replace('user=1', 'user=2', $signed)));

        // منقضی → رد
        $expired = $security->sign('https://example.com/x', new \DateTimeImmutable('-1 hour'));
        $this->assertFalse($security->validateSignature($expired));

        // بدون expires → معتبر
        $noExpiry = $security->sign('https://example.com/y');
        $this->assertTrue($security->validateSignature($noExpiry));
    }

    // ─── T-10: Normalizer ─────────────────────────────────────────────────

    public function testNormalizerStripsBidiAndZeroWidth(): void
    {
        $normalizer = new InputNormalizer();

        // bidi override (Trojan Source)
        $this->assertSame('abc', $normalizer->normalize("a\u{202E}b\u{202C}c"));

        // zero-width space
        $this->assertSame('سلام', $normalizer->normalize("س\u{200B}لام"));

        // 🔒 ZWNJ فارسی حفظ می‌شود
        $this->assertSame('می‌شود', $normalizer->normalize('می‌شود'));

        // ارقام فارسی/عربی → لاتین
        $this->assertSame('12345', $normalizer->normalize('۱۲۳۴۵'));
        $this->assertSame('0912', $normalizer->normalize('٠٩١٢'));

        // null byte + trim
        $this->assertSame('clean', $normalizer->normalize("  cle\x00an  "));

        // ی/ک عربی → فارسی
        $this->assertSame('کتابی', $normalizer->normalize('كتابي'));
    }

    public function testNormalizeArrayRecursive(): void
    {
        $normalizer = new InputNormalizer();
        $out = $normalizer->normalizeArray(['a' => '۱۲', 'b' => ['c' => "x\u{200B}y"], 'n' => 5]);

        $this->assertSame('12', $out['a']);
        $this->assertSame('xy', $out['b']['c']);
        $this->assertSame(5, $out['n']);
    }
}

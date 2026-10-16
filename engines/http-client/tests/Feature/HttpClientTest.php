<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Tests\Feature;

require_once __DIR__ . '/../Fixtures/FakeHttpTransport.php';

use Tavaan\Core\Application\Application;
use Tavaan\Engine\HttpClient\CircuitBreaker;
use Tavaan\Engine\HttpClient\Exceptions\CircuitOpenException;
use Tavaan\Engine\HttpClient\Exceptions\ConnectionException;
use Tavaan\Engine\HttpClient\Exceptions\HttpClientException;
use Tavaan\Engine\HttpClient\Exceptions\RequestException;
use Tavaan\Engine\HttpClient\Factory;
use Tavaan\Engine\HttpClient\PendingRequest;
use Tavaan\Engine\HttpClient\Request;
use Tavaan\Engine\HttpClient\Response;
use Tavaan\Engine\HttpClient\Ssrf\InternalSsrfGuard;
use Tavaan\Engine\HttpClient\Tests\Fixtures\FakeHttpTransport;
use Tavaan\Engine\HttpClient\Transport\StreamTransport;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور HttpClient — DOC-064 §9 (T-01..T-09).
 */
final class HttpClientTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        Clock::setTestNow(null);
        $this->app = \tavaan_test_app();
        $this->app->make('config')->set('cache.default', 'array');
        $this->app->make('config')->set('http-client.retry.times', 1);
        $this->app->make('config')->set('http-client.ssrf_protection', true);
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
    }

    /** Factory با transport ساختگی و SSRF غیرفعال (URLهای تست). */
    private function factoryWith(FakeHttpTransport $transport, bool $ssrf = false): Factory
    {
        $factory = new Factory(
            $this->app->make('config'),
            $this->app,
            $transport,
            $ssrf ? null : new InternalSsrfGuard(['allow_private' => true]),
            $this->app->make('cache'),
        );

        return $factory->withSleeper(static function (int $ms): void {
            // بدون خواب واقعی در تست
        });
    }

    // ─── T-01: verbها ────────────────────────────────────────────────────

    public function testVerbsSendCorrectMethodAndBody(): void
    {
        $transport = new FakeHttpTransport([FakeHttpTransport::response(200, '{"ok":true}', ['content-type' => 'application/json'])]);
        $http = $this->factoryWith($transport);

        $response = $http->get('http://api.test/users/1');
        $this->assertSame(200, $response->status());
        $this->assertTrue($response->successful());
        $this->assertSame(['ok' => true], $response->json());
        $this->assertTrue($response->json('ok'));

        $http->post('http://api.test/users', ['name' => 'علی']);
        $http->put('http://api.test/users/1', ['name' => 'رضا']);
        $http->patch('http://api.test/users/1', ['name' => 'سارا']);
        $http->delete('http://api.test/users/1');

        $this->assertSame(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], \array_column($transport->sent, 'method'));

        // بدنهٔ POST = JSON
        $this->assertStringContainsString('"name":"علی"', (string) $transport->sent[1]['body']);
        $this->assertSame('application/json', $transport->sent[1]['headers']['Content-Type']);

        // DELETE بدون بدنه
        $this->assertNull($transport->sent[4]['body']);
    }

    public function testFluentHeadersTokenAndQuery(): void
    {
        $transport = new FakeHttpTransport();
        $http = $this->factoryWith($transport);

        $http->withToken('abc123')
            ->withHeaders(['X-Trace' => 'tvn'])
            ->acceptJson()
            ->withQueryParameters(['page' => 2])
            ->get('http://api.test/items');

        $sent = $transport->sent[0];
        $this->assertSame('Bearer abc123', $sent['headers']['Authorization']);
        $this->assertSame('tvn', $sent['headers']['X-Trace']);
        $this->assertSame('application/json', $sent['headers']['Accept']);
        $this->assertStringContainsString('page=2', $sent['url']);
        $this->assertStringContainsString('Tavaan/1.0', $sent['headers']['User-Agent']);
    }

    public function testResponseHelpers(): void
    {
        $transport = new FakeHttpTransport([
            FakeHttpTransport::response(404, '{"error":"nf"}', ['content-type' => 'application/json', 'set-cookie' => 'sid=42; Path=/']),
        ]);
        $http = $this->factoryWith($transport);

        $response = $http->get('http://api.test/missing');
        $this->assertTrue($response->failed());
        $this->assertTrue($response->clientError());
        $this->assertSame('nf', $response->json('error'));
        $this->assertNull($response->json('nope'));
        $this->assertSame('default', $response->json('nope', 'default'));
        $this->assertSame('application/json', $response->header('Content-Type'));
        $this->assertSame(['sid' => '42'], $response->cookies());

        try {
            $response->throw();
            $this->fail('throw() باید استثنا دهد');
        } catch (RequestException $e) {
            $this->assertSame(404, $e->response->status());
        }

        // throwIf
        $thrown = false;
        try {
            $response->throwIf(fn (Response $r): bool => $r->status() === 404);
        } catch (RequestException) {
            $thrown = true;
        }
        $this->assertTrue($thrown);
    }

    // ─── T-02: fake + assertSent ─────────────────────────────────────────

    public function testFakeWithPatternsAndAssertions(): void
    {
        $http = $this->app->make(Factory::class);

        $http->fake([
            'api.example.com/*' => Factory::response(['ok' => true], 200),
            'http://slow.test/*' => Factory::response('timeout', 504),
            'seq.test/*' => [Factory::response('first'), Factory::response('second')],
        ]);

        $response = $http->get('https://api.example.com/users/1');
        $this->assertSame(['ok' => true], $response->json());

        $this->assertSame(504, $http->get('http://slow.test/x')->status());

        // sequence
        $this->assertSame('first', $http->get('http://seq.test/a')->body());
        $this->assertSame('second', $http->get('http://seq.test/b')->body());
        $this->assertSame('second', $http->get('http://seq.test/c')->body());   // تکرار آخرین

        // الگوی بدون مطابقت → ۲۰۰ خالی
        $this->assertSame('', $http->get('http://other.test/')->body());

        $http->assertSent(fn (Request $r): bool => $r->urlIs('https://api.example.com/users/1'));
        $http->assertSent(fn (Request $r): bool => $r->method() === 'GET' && $r->urlIs('api.example.com/*'));
        $http->assertNotSent(fn (Request $r): bool => $r->method() === 'POST');
        $http->assertSentCount(6);
    }

    public function testFakeWithClosure(): void
    {
        $http = $this->app->make(Factory::class);
        $http->fake(['echo.test/*' => fn (Request $request): Response => Factory::response(['url' => $request->url()], 201)]);

        $response = $http->post('http://echo.test/hello', ['a' => 1]);

        $this->assertSame(201, $response->status());
        $this->assertSame('http://echo.test/hello', $response->json('url'));
    }

    public function testAssertSentFailureThrows(): void
    {
        $http = $this->app->make(Factory::class);
        $http->fake();
        $http->get('http://x.test/');

        try {
            $http->assertSent(fn (Request $r): bool => $r->urlIs('http://never.test/*'));
            $this->fail('assertSent باید شکست بخورد');
        } catch (HttpClientException $e) {
            $this->assertSame('http_client.assertion_failed', $e->messageId);
        }
    }

    // ─── T-03: retry ─────────────────────────────────────────────────────

    public function testRetryOnConnectionFailure(): void
    {
        $transport = new FakeHttpTransport([
            ConnectionException::connectionFailed('http://api.test/x', 'timeout'),
            ConnectionException::connectionFailed('http://api.test/x', 'timeout'),
            FakeHttpTransport::response(200, 'finally'),
        ]);
        $http = $this->factoryWith($transport);

        $response = $http->retry(3, 1)->get('http://api.test/x');

        $this->assertSame('finally', $response->body());
        $this->assertSame(3, $transport->callCount());
    }

    public function testRetryExhaustedThrowsConnectionException(): void
    {
        $transport = new FakeHttpTransport([
            ConnectionException::connectionFailed('http://api.test/x', 'down'),
        ]);
        $http = $this->factoryWith($transport);

        try {
            $http->retry(2, 1)->get('http://api.test/x');
            $this->fail('پس از اتمام تلاش‌ها باید استثنا پرتاب شود');
        } catch (ConnectionException $e) {
            $this->assertSame(2, $transport->callCount());
        }
    }

    public function testRetryOn5xxAnd429(): void
    {
        $transport = new FakeHttpTransport([
            FakeHttpTransport::response(503, 'unavailable'),
            FakeHttpTransport::response(429, 'slow down', ['retry-after' => '0']),
            FakeHttpTransport::response(200, 'ok'),
        ]);
        $http = $this->factoryWith($transport);

        $response = $http->retry(3, 1)->get('http://api.test/x');

        $this->assertSame('ok', $response->body());
        $this->assertSame(3, $transport->callCount());
    }

    public function testBackoffIsExponentialWithCap(): void
    {
        $handler = new \Tavaan\Engine\HttpClient\RetryHandler(5, 100, 30_000, null, static fn (int $base): int => 0);

        $this->assertSame(100, $handler->backoffMs(1));
        $this->assertSame(200, $handler->backoffMs(2));
        $this->assertSame(400, $handler->backoffMs(3));
        $this->assertSame(800, $handler->backoffMs(4));
    }

    // ─── T-04: circuit breaker ───────────────────────────────────────────

    public function testCircuitBreakerOpensAfterThreshold(): void
    {
        Clock::setTestNow('2026-06-01 12:00:00');

        $cache = $this->app->make('cache');
        $breaker = new CircuitBreaker($cache, threshold: 3, window: 60, cooldown: 30);

        $failing = static fn (): never => throw ConnectionException::connectionFailed('svc', 'down');

        // ۳ خطا → open
        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->call('api.test', $failing);
            } catch (ConnectionException) {
            }
        }
        $this->assertSame('open', $breaker->state('api.test'));

        // درخواست بعدی بدون اجرا رد می‌شود
        try {
            $breaker->call('api.test', static fn (): string => 'should not run');
            $this->fail('مدار باز باید درخواست را رد کند');
        } catch (CircuitOpenException $e) {
            $this->assertSame('http_client.circuit_open', $e->messageId);
        }

        // پس از cooldown → half-open؛ یک موفقیت → closed
        Clock::setTestNow('2026-06-01 12:01:00');   // +60s > 30s cooldown
        $this->assertSame('half-open', $breaker->state('api.test'));

        $result = $breaker->call('api.test', static fn (): string => 'recovered');
        $this->assertSame('recovered', $result);
        $this->assertSame('closed', $breaker->state('api.test'));
    }

    // ─── T-05: SSRF ──────────────────────────────────────────────────────

    public function testSsrfBlocksPrivateAndReservedIps(): void
    {
        $guard = new InternalSsrfGuard();

        $blocked = [
            'http://127.0.0.1/admin',
            'http://10.0.0.5/internal',
            'http://172.16.0.1/',
            'http://192.168.1.1/',
            'http://169.254.169.254/latest/meta-data/',   // ☁️ cloud metadata
            'http://[::1]/',
            'file:///etc/passwd',
            'gopher://127.0.0.1:6379/_INFO',
            'http://0.0.0.0/',
        ];

        foreach ($blocked as $url) {
            $this->assertFalse($guard->isAllowed($url), 'باید مسدود شود: ' . $url);
        }

        // IP عمومی مجاز است (بدون DNS)
        $this->assertTrue($guard->isAllowed('http://93.184.216.34/'));

        // allowlist دامنه
        $withList = new InternalSsrfGuard(['allowed_hosts' => ['internal.corp', '*.tavaan.dev']]);
        $this->assertTrue($withList->isAllowed('http://internal.corp/api'));
        $this->assertTrue($withList->isAllowed('http://api.tavaan.dev/x'));

        // allow_private (dev)
        $dev = new InternalSsrfGuard(['allow_private' => true]);
        $this->assertTrue($dev->isAllowed('http://127.0.0.1:8000/'));
    }

    public function testSsrfBlocksRequestBeforeTransport(): void
    {
        $transport = new FakeHttpTransport();
        $http = $this->factoryWith($transport, ssrf: true);

        try {
            $http->get('http://169.254.169.254/latest/meta-data/');
            $this->fail('SSRF باید پیش از ارسال رد کند');
        } catch (\Tavaan\Core\Exceptions\TavaanException $e) {
            // guard داخلی (http_client.ssrf_blocked) یا موتور security (security.ssrf.*)
            $this->assertStringContainsString('ssrf', $e->messageId);
        }

        $this->assertSame(0, $transport->callCount());   // هرگز ارسال نشد
    }

    // ─── T-06: redirect با SSRF re-check ─────────────────────────────────

    public function testRedirectIsFollowed(): void
    {
        $transport = new FakeHttpTransport([
            FakeHttpTransport::response(302, '', ['location' => 'http://api.test/final']),
            FakeHttpTransport::response(200, 'final-body'),
        ]);
        $http = $this->factoryWith($transport);

        $response = $http->get('http://api.test/start');

        $this->assertSame('final-body', $response->body());
        $this->assertSame(2, $transport->callCount());
        $this->assertSame('http://api.test/final', $transport->sent[1]['url']);
    }

    public function testRedirectToPrivateIpIsBlocked(): void
    {
        $transport = new FakeHttpTransport([
            FakeHttpTransport::response(302, '', ['location' => 'http://169.254.169.254/latest/meta-data/']),
        ]);
        $http = $this->factoryWith($transport, ssrf: true);

        try {
            // 🔒 hop اول IP عمومی است (بدون نیاز به DNS) — redirect به metadata باید رد شود
            $http->get('http://93.184.216.34/start');
            $this->fail('redirect به IP خصوصی باید مسدود شود');
        } catch (\Tavaan\Core\Exceptions\TavaanException $e) {
            $this->assertStringContainsString('ssrf', $e->messageId);
        }
    }

    public function testTooManyRedirectsRejected(): void
    {
        $responses = [];
        for ($i = 0; $i < 8; $i++) {
            $responses[] = FakeHttpTransport::response(302, '', ['location' => 'http://api.test/hop' . $i]);
        }
        $transport = new FakeHttpTransport($responses);
        $http = $this->factoryWith($transport);

        try {
            $http->maxRedirects(5)->get('http://api.test/start');
            $this->fail('باید پس از ۵ hop متوقف شود');
        } catch (HttpClientException $e) {
            $this->assertSame('http_client.too_many_redirects', $e->messageId);
        }
    }

    // ─── T-07: Pool ──────────────────────────────────────────────────────

    public function testPoolReturnsNamedResponses(): void
    {
        $transport = new FakeHttpTransport([
            FakeHttpTransport::response(200, '{"id":1}'),
            FakeHttpTransport::response(200, '{"id":2}'),
        ]);
        $http = $this->factoryWith($transport);

        $responses = $http->pool(function ($pool): void {
            $pool->as('user')->get('http://api.test/user');
            $pool->as('posts')->get('http://api.test/posts');
        });

        $this->assertArrayHasKey('user', $responses);
        $this->assertArrayHasKey('posts', $responses);
        $this->assertSame(1, $responses['user']->json('id'));
        $this->assertSame(2, $responses['posts']->json('id'));
        $this->assertSame(2, $transport->callCount());
    }

    public function testPoolCapturesConnectionErrors(): void
    {
        $transport = new FakeHttpTransport([
            ConnectionException::connectionFailed('http://api.test/a', 'down'),
            FakeHttpTransport::response(200, 'b-ok'),
        ]);
        $http = $this->factoryWith($transport);

        $responses = $http->pool(function ($pool): void {
            $pool->as('a')->retry(1, 1)->get('http://api.test/a');
            $pool->as('b')->get('http://api.test/b');
        });

        $this->assertInstanceOf(ConnectionException::class, $responses['a']);
        $this->assertSame('b-ok', $responses['b']->body());
    }

    // ─── T-08: JSON/form/multipart ──────────────────────────────────────

    public function testFormAndMultipartBodies(): void
    {
        $transport = new FakeHttpTransport();
        $http = $this->factoryWith($transport);

        $http->asForm()->post('http://api.test/form', ['user' => 'علی', 'pass' => 'x y']);
        $this->assertSame('application/x-www-form-urlencoded', $transport->sent[0]['headers']['Content-Type']);
        $this->assertStringContainsString('user=%D8%B9%D9%84%DB%8C', (string) $transport->sent[0]['body']);
        $this->assertStringContainsString('pass=x+y', (string) $transport->sent[0]['body']);

        $http->attach('invoice', 'PDF-BYTES', 'invoice.pdf', ['Content-Type' => 'application/pdf'])
            ->post('http://api.test/upload', ['title' => 'فاکتور']);

        $body = (string) $transport->sent[1]['body'];
        $contentType = $transport->sent[1]['headers']['Content-Type'];
        $this->assertStringContainsString('multipart/form-data; boundary=tavaan-', $contentType);
        $this->assertStringContainsString('name="title"', $body);
        $this->assertStringContainsString('فاکتور', $body);
        $this->assertStringContainsString('filename="invoice.pdf"', $body);
        $this->assertStringContainsString('PDF-BYTES', $body);
        $this->assertStringContainsString('Content-Type: application/pdf', $body);
    }

    // ─── T-09: transport fallback ────────────────────────────────────────

    public function testTransportSelectionFallsBackToStream(): void
    {
        $transport = Factory::defaultTransport();

        if (\extension_loaded('curl')) {
            $this->assertSame('curl', $transport->name());
        } else {
            $this->assertInstanceOf(StreamTransport::class, $transport);
            $this->assertSame('stream', $transport->name());
        }
    }

    // ─── یکپارچگی با اپلیکیشن ────────────────────────────────────────────

    public function testFacadeStyleUsageThroughContainer(): void
    {
        /** @var Factory $http */
        $http = $this->app->make('http.client');
        $http->fake(['users.test/*' => Factory::response(['name' => 'مریم'], 200)]);

        $response = $http->withToken('t')->get('http://users.test/me');

        $this->assertSame('مریم', $response->json('name'));
        $http->assertSent(fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer t'));
    }

    public function testOptionsArePassedToTransport(): void
    {
        $transport = new FakeHttpTransport();
        $http = $this->factoryWith($transport);

        $http->timeout(77)->withOptions(['max_size' => 123])->get('http://api.test/x');

        $this->assertSame(77, $transport->sent[0]['options']['timeout']);
        $this->assertSame(123, $transport->sent[0]['options']['max_size']);
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Http\Cookie\Cookie;
use Tavaan\Engine\Http\HttpKernel;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Http\Routing\Router;
use Tavaan\Testing\TestCase;

/**
 * تست end-to-end چرخهٔ درخواست (DOC-025 P7..P14) — درون‌فرایندی.
 */
final class HttpKernelTest extends TestCase
{
    private Application $app;

    private HttpKernel $kernel;

    private Router $router;

    protected function setUp(): void
    {
        $this->app = \tavaan_test_app();
        $this->app->make('config')->set('session.driver', 'array');
        $this->app->make('config')->set('log.default', 'array');
        $this->app->make('config')->set('app.debug', true);

        $this->kernel = $this->app->make(HttpKernel::class);
        $this->router = $this->app->make('router');

        $this->router->get('/test/plain', static fn (): string => 'plain-body');
        $this->router->get('/test/json', static fn (): array => ['ok' => true, 'fa' => 'سلام']);
        $this->router->get('/test/boom', static function (): never {
            throw new \RuntimeException('kernel-test-boom');
        });
        $this->router->post('/test/echo', static fn (Request $r): string => 'echo:' . (string) $r->input('msg'));
        $this->router->get('/test/token', static function (Request $r): Response {
            $session = $r->session();

            return Response::json(['token' => $session?->token() ?? '']);
        });
        $this->router->get('/test/count', static function (Request $r): Response {
            $session = $r->session();
            $count = $session !== null ? $session->increment('hits') : -1;

            return Response::json(['hits' => $count]);
        });
    }

    private function handle(Request $request): Response
    {
        $response = $this->kernel->handle($request);
        \assert($response instanceof Response);
        $this->kernel->terminate($request, $response);

        return $response;
    }

    public function testPlainResponse(): void
    {
        $response = $this->handle(Request::create('http://localhost/test/plain'));
        $this->assertSame(200, $response->statusCode());
        $this->assertSame('plain-body', $response->body());
    }

    public function testJsonNormalization(): void
    {
        $response = $this->handle(Request::create('http://localhost/test/json'));
        $this->assertStringContainsString('application/json', (string) $response->header('Content-Type'));
        $decoded = \json_decode($response->body(), true);
        $this->assertTrue($decoded['ok']);
        $this->assertSame('سلام', $decoded['fa']);
    }

    public function testNotFoundRenders404(): void
    {
        $response = $this->handle(Request::create('http://localhost/definitely-not-here-xyz'));
        $this->assertSame(404, $response->statusCode());
    }

    public function testMethodNotAllowedRenders405WithAllow(): void
    {
        // CSRF را برای این تست غیرفعال می‌کنیم تا 405 خالص بررسی شود
        $this->disableCsrf();
        $response = $this->handle(Request::create('http://localhost/test/plain', 'POST'));
        $this->assertSame(405, $response->statusCode());
        $this->assertStringContainsString('GET', (string) $response->header('Allow'));
    }

    public function testExceptionRenders500AndReports(): void
    {
        $response = $this->handle(Request::create('http://localhost/test/boom'));
        $this->assertSame(500, $response->statusCode());
        $this->assertStringContainsString('kernel-test-boom', $response->body());   // debug=true
    }

    public function testJsonExceptionNegotiation(): void
    {
        $request = Request::create('http://localhost/test/boom', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $response = $this->handle($request);
        $this->assertSame(500, $response->statusCode());
        $this->assertStringContainsString('application/problem+json', (string) $response->header('Content-Type'));
        $body = \json_decode($response->body(), true);
        $this->assertSame(500, $body['status']);
        $this->assertSame('Internal Server Error', $body['title']);
    }

    public function testProductionHidesDetails(): void
    {
        $this->app->make('config')->set('app.debug', false);
        $this->app->make('config')->set('error.rendering.debug', false);
        // ExceptionHandler از قبل ساخته شده — نمونهٔ تازه با config جدید
        $handler = new \Tavaan\Engine\Error\ExceptionHandler(
            logger: $this->app->make('log'),
            config: $this->app->make('config'),
        );
        $result = $handler->render(new \RuntimeException('super-secret-detail'), 'json');
        $this->assertStringNotContainsString('super-secret-detail', $result->body);
        $this->app->make('config')->set('app.debug', true);
        $this->app->make('config')->set('error.rendering.debug', true);
    }

    public function testSessionAcrossRequests(): void
    {
        $first = $this->handle(Request::create('http://localhost/test/count'));
        $hits1 = \json_decode($first->body(), true)['hits'];

        // کوکی نشست از پاسخ اول
        $cookies = [];
        foreach ($first->cookies() as $cookie) {
            $cookies[$cookie->name()] = $cookie->value();
        }
        $this->assertNotEmpty($cookies);

        $second = $this->handle(Request::create('http://localhost/test/count', 'GET', [], $cookies));
        $hits2 = \json_decode($second->body(), true)['hits'];

        $this->assertSame(1, $hits1);
        $this->assertSame(2, $hits2);
    }

    public function testSessionCookieFlags(): void
    {
        $response = $this->handle(Request::create('http://localhost/test/count'));
        $sessionCookie = null;
        foreach ($response->cookies() as $cookie) {
            if ($cookie->name() === 'tavaan_session') {
                $sessionCookie = $cookie;
            }
        }

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->httpOnly);
        $this->assertSame('Lax', $sessionCookie->sameSite);
        $this->assertTrue(\Tavaan\Engine\Session\Session::isValidId($sessionCookie->value()));
    }

    public function testCsrfRejectsPostWithoutToken(): void
    {
        $response = $this->handle(Request::create('http://localhost/test/echo', 'POST', ['msg' => 'hi']));
        $this->assertSame(419, $response->statusCode());
    }

    public function testCsrfAcceptsValidToken(): void
    {
        // دریافت توکن از یک GET (session شروع و token ذخیره می‌شود)
        $warm = $this->handle(Request::create('http://localhost/test/token'));
        $token = (string) (\json_decode($warm->body(), true)['token'] ?? '');
        $this->assertNotSame('', $token);
        $cookies = [];
        foreach ($warm->cookies() as $cookie) {
            $cookies[$cookie->name()] = $cookie->value();
        }

        $response = $this->handle(Request::create('http://localhost/test/echo', 'POST', ['msg' => 'hi', '_token' => $token], $cookies));
        $this->assertSame(200, $response->statusCode());
        $this->assertSame('echo:hi', $response->body());
    }

    public function testCsrfWrongTokenRejected(): void
    {
        $warm = $this->handle(Request::create('http://localhost/test/token'));
        $cookies = [];
        foreach ($warm->cookies() as $cookie) {
            $cookies[$cookie->name()] = $cookie->value();
        }

        $response = $this->handle(Request::create('http://localhost/test/echo', 'POST', ['msg' => 'hi', '_token' => 'wrong-token'], $cookies));
        $this->assertSame(419, $response->statusCode());
    }

    public function testEtagConditional(): void
    {
        $first = $this->handle(Request::create('http://localhost/test/plain'));
        $etag = (string) $first->header('ETag');
        $this->assertNotSame('', $etag);

        $second = $this->handle(Request::create('http://localhost/test/plain', 'GET', [], [], [], ['HTTP_IF_NONE_MATCH' => $etag]));
        $this->assertSame(304, $second->statusCode());
        $this->assertSame('', $second->body());
    }

    public function testSecurityHeadersPresent(): void
    {
        $response = $this->handle(Request::create('http://localhost/test/plain'));
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->header('X-Frame-Options'));
        $this->assertNotNull($response->header('Content-Security-Policy'));
        $this->assertNotNull($response->header('traceparent'));
    }

    public function testMaintenanceMode(): void
    {
        $downFile = $this->app->basePath('storage/framework/down');
        \file_put_contents($downFile, (string) \json_encode(['retry' => 60, 'message' => 'تعمیرات']));
        try {
            $response = $this->handle(Request::create('http://localhost/test/plain'));
            $this->assertSame(503, $response->statusCode());
            $this->assertSame('60', $response->header('Retry-After'));
        } finally {
            @\unlink($downFile);
        }

        $ok = $this->handle(Request::create('http://localhost/test/plain'));
        $this->assertSame(200, $ok->statusCode());
    }

    public function testTerminateCleansScope(): void
    {
        $request = Request::create('http://localhost/test/plain');
        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        // پس از terminate هیچ scope فعالی نمی‌ماند (Resident safety)
        $this->assertNull($this->app->currentScope());
    }

    public function testWelcomeRouteFromFiles(): void
    {
        // routes/web.php توسط provider بارگذاری شده
        $this->assertTrue($this->router->has('home'));
        $response = $this->handle(Request::create('http://localhost/'));
        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('سلام، تَوان', $response->body());
    }

    private function disableCsrf(): void
    {
        $global = (array) $this->app->make('config')->get('http.middleware.global', []);
        $filtered = \array_values(\array_filter(
            $global,
            static fn (string $mw): bool => !\str_contains($mw, 'CsrfVerify'),
        ));
        $this->app->make('config')->set('http.middleware.global', $filtered);
        // kernel از قبل config را خوانده — نمونهٔ تازه
        $this->kernel = $this->app->make('http.kernel');
        $this->app->forgetInstance('http.kernel');
        $this->kernel = $this->app->make(HttpKernel::class);
    }
}

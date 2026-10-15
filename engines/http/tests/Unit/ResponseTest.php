<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Tests\Unit;

use Tavaan\Engine\Container\Container;
use Tavaan\Engine\Http\Cookie\Cookie;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Http\ResponseFactory;
use Tavaan\Engine\Http\ResponsePreparer;
use Tavaan\Testing\TestCase;

/**
 * تست Response/Preparer/Factory — DOC-039 §12 (T-08..T-17).
 */
final class ResponseTest extends TestCase
{
    public function testImmutableStatusAndHeaders(): void
    {
        $r = new Response('body', 200);
        $r2 = $r->withStatus(404)->withHeader('X-A', '1');

        $this->assertSame(200, $r->statusCode());
        $this->assertSame('', $r->header('X-A') ?? '');
        $this->assertSame(404, $r2->statusCode());
        $this->assertSame('1', $r2->header('X-A'));
        $this->assertNotSame($r, $r2);
    }

    public function testJsonResponse(): void
    {
        $r = Response::json(['msg' => 'سلام', 'ok' => true]);
        $this->assertSame(200, $r->statusCode());
        $this->assertStringContainsString('application/json', (string) $r->header('Content-Type'));
        $this->assertStringContainsString('سلام', $r->body());           // unescaped unicode
        $this->assertStringNotContainsString('\\u0633', $r->body());

        $decoded = \json_decode($r->body(), true);
        $this->assertTrue($decoded['ok']);
    }

    public function testWithJsonOnExisting(): void
    {
        $r = (new Response())->withJson(['a' => 1], 201);
        $this->assertSame(201, $r->statusCode());
        $this->assertSame('{"a":1}', $r->body());
    }

    public function testCookiesQueue(): void
    {
        $r = (new Response())->cookie(new Cookie('theme', 'dark'));
        $this->assertCount(1, $r->cookies());
        $this->assertSame('theme', $r->cookies()[0]->name());
        // پیش‌فرض‌های امن (T-04)
        $this->assertTrue($r->cookies()[0]->httpOnly);
        $this->assertSame('Lax', $r->cookies()[0]->sameSite);
    }

    public function testStatusGroups(): void
    {
        $this->assertTrue((new Response('', 200))->isSuccessful());
        $this->assertTrue((new Response('', 302))->isRedirect());
        $this->assertTrue((new Response('', 404))->isClientError());
        $this->assertTrue((new Response('', 500))->isServerError());
    }

    /** T-08: ETag + If-None-Match → 304 */
    public function testEtagConditional304(): void
    {
        $preparer = new ResponsePreparer(['etag' => ['enabled' => true, 'algorithm' => 'xxh128'], 'compression' => ['enabled' => false]]);
        $request = Request::create('/x');
        $response = $preparer->prepare(new Response('hello world'), $request);

        $etag = $response->header('ETag');
        $this->assertNotNull($etag);

        $conditional = Request::create('/x', 'GET', [], [], [], ['HTTP_IF_NONE_MATCH' => (string) $etag]);
        $notModified = $preparer->prepare(new Response('hello world'), $conditional);
        $this->assertSame(304, $notModified->statusCode());
        $this->assertSame('', $notModified->body());
    }

    /** T-09: If-Modified-Since → 304 */
    public function testLastModifiedConditional(): void
    {
        $preparer = new ResponsePreparer(['etag' => ['enabled' => false], 'compression' => ['enabled' => false]]);
        $response = (new Response('x'))->withHeader('Last-Modified', \gmdate('D, d M Y H:i:s', \time() - 3600) . ' GMT');
        $request = Request::create('/x', 'GET', [], [], [], ['HTTP_IF_MODIFIED_SINCE' => \gmdate('D, d M Y H:i:s') . ' GMT']);

        $this->assertSame(304, $preparer->prepare($response, $request)->statusCode());
    }

    /** T-10: Compression فقط type فشرده‌شدنی و ≥ min_size */
    public function testCompressionConditions(): void
    {
        $preparer = new ResponsePreparer(['etag' => ['enabled' => false], 'compression' => ['enabled' => true, 'min_size' => 100, 'level' => 5, 'algorithms' => ['gzip', 'deflate']]]);
        $request = Request::create('/x', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip, deflate']);

        $bigHtml = new Response(\str_repeat('تست ', 200), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        $compressed = $preparer->prepare($bigHtml, $request);
        $this->assertSame('gzip', $compressed->header('Content-Encoding'));
        $this->assertLessThan(\strlen($bigHtml->body()), \strlen($compressed->body()));

        // small body → no compression
        $small = new Response('hi', 200, ['Content-Type' => 'text/html']);
        $this->assertNull($preparer->prepare($small, $request)->header('Content-Encoding'));

        // non-compressible type
        $img = new Response(\str_repeat('x', 5000), 200, ['Content-Type' => 'image/png']);
        $this->assertNull($preparer->prepare($img, $request)->header('Content-Encoding'));

        // no accept-encoding
        $plain = Request::create('/x');
        $this->assertNull($preparer->prepare($bigHtml, $plain)->header('Content-Encoding'));
    }

    public function testContentLengthAdded(): void
    {
        $preparer = new ResponsePreparer(['etag' => ['enabled' => false], 'compression' => ['enabled' => false]]);
        $r = $preparer->prepare(new Response('12345'), Request::create('/x'));
        $this->assertSame('5', $r->header('Content-Length'));
    }

    /** T-17: ResponseFactory */
    public function testResponseFactory(): void
    {
        $factory = new ResponseFactory(new Container());

        $this->assertSame('OK', $factory->make('OK')->body());
        $this->assertSame(204, $factory->noContent()->statusCode());

        $json = $factory->json(['x' => 1], 201);
        $this->assertSame(201, $json->statusCode());

        $problem = $factory->problem('validation.failed', 422, ['errors' => ['a' => ['b']]]);
        $this->assertSame(422, $problem->statusCode());
        $this->assertStringContainsString('problem+json', (string) $problem->header('Content-Type'));

        $tmpDir = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/storage/temp' : \sys_get_temp_dir();
        $tmp = $tmpDir . '/tvn-response-test-' . \getmypid() . '.txt';
        \file_put_contents($tmp, 'file-content');
        $file = $factory->file($tmp);
        $this->assertSame('file-content', $file->body());
        $dl = $factory->download($tmp, 'my file.txt');
        $this->assertStringContainsString('attachment', (string) $dl->header('Content-Disposition'));
        @\unlink($tmp);

        $stream = $factory->stream(static function (): void { echo 'chunk1'; echo 'chunk2'; });
        $this->assertSame('chunk1chunk2', $stream->body());
    }

    /** file() path traversal guard */
    public function testFileOutsideBasePathRejected(): void
    {
        $factory = new ResponseFactory(new Container());
        $this->expectException(\Tavaan\Core\Exceptions\Filesystem\PathTraversalException::class);
        $factory->file('/etc/passwd');
    }

    public function testRedirectorOpenRedirectBlocked(): void
    {
        $container = new Container();
        $router = new \Tavaan\Engine\Http\Routing\Router($container);
        $gen = new \Tavaan\Engine\Http\Routing\UrlGenerator($router, null, 'http://myapp.local');
        $redirector = new \Tavaan\Engine\Http\Redirector($container, $gen);

        $r = $redirector->to('/dashboard');
        $this->assertSame(302, $r->statusCode());
        $this->assertStringContainsString('/dashboard', (string) $r->header('Location'));

        $this->expectException(\InvalidArgumentException::class);
        $redirector->to('https://evil.example.com/phish');
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Tests\Unit;

use Tavaan\Core\Exceptions\Http\BadRequestException;
use Tavaan\Engine\Http\Exceptions\HeaderInjectionException;
use Tavaan\Engine\Http\Request;
use Tavaan\Testing\TestCase;

/**
 * تست Request — سناریوهای DOC-039 §12 (AC-HTTP-01/05).
 */
final class RequestTest extends TestCase
{
    /** T-01: create با متدها */
    public function testCreateWithMethods(): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'] as $method) {
            $r = Request::create('/x', $method);
            $this->assertSame($method, $r->method());
        }
    }

    /** T-02: Method override */
    public function testMethodOverride(): void
    {
        $r = Request::create('/x', 'POST', ['_method' => 'PUT']);
        $this->assertSame('PUT', $r->method());
        $this->assertSame('POST', $r->realMethod());

        $r2 = Request::create('/x', 'POST', [], [], [], ['HTTP_X_HTTP_METHOD_OVERRIDE' => 'DELETE']);
        $this->assertSame('DELETE', $r2->method());

        // GET با _method نادیده گرفته می‌شود
        $r3 = Request::create('/x', 'GET', ['_method' => 'DELETE']);
        $this->assertSame('GET', $r3->method());
    }

    /** T-03: JSON عمیق → خطا */
    public function testJsonTooDeepRejected(): void
    {
        $deep = '{"a":' . \str_repeat('{"a":', 40) . '1' . \str_repeat('}', 41) . '}';
        $r = Request::create('/x', 'POST', [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], $deep);
        $this->expectException(BadRequestException::class);
        $r->json();
    }

    public function testInvalidJsonRejected(): void
    {
        $r = Request::create('/x', 'POST', [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{bad json');
        $this->expectException(BadRequestException::class);
        $r->json();
    }

    /** T-04: input priority: query < post < json */
    public function testInputPriority(): void
    {
        $r = Request::create('/x?name=query&only_query=1', 'POST', ['name' => 'post'], [], [], ['HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame('post', $r->input('name'));
        $this->assertSame('1', $r->input('only_query'));
        $this->assertSame('query', $r->query('name'));
        $this->assertSame('post', $r->post('name'));

        $json = Request::create('/x?name=query', 'POST', [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"name":"json"}');
        $this->assertSame('json', $json->input('name'));
    }

    /** T-05: Immutable */
    public function testImmutability(): void
    {
        $r = Request::create('/x');
        $r2 = $r->withAttribute('user', 5);
        $this->assertNull($r->attribute('user'));
        $this->assertSame(5, $r2->attribute('user'));
        $this->assertNotSame($r, $r2);

        $r3 = $r->withHeader('X-Test', 'v');
        $this->assertNull($r->header('X-Test'));
        $this->assertSame('v', $r3->header('X-Test'));
    }

    /** T-11: Header injection */
    public function testHeaderInjectionRejected(): void
    {
        $r = Request::create('/x');
        $this->expectException(HeaderInjectionException::class);
        $r->withHeader("X-Evil\r\nInjected: yes", 'v');
    }

    public function testHeaderValueInjectionRejected(): void
    {
        $r = Request::create('/x');
        $this->expectException(HeaderInjectionException::class);
        $r->withHeader('X-Test', "value\r\nSet-Cookie: hacked=1");
    }

    /** T-12: CL + TE → smuggling 400 */
    public function testRequestSmugglingRejected(): void
    {
        // با شبیه‌سازی superglobals
        $backup = $_SERVER;
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/x',
            'CONTENT_LENGTH' => '5',
            'HTTP_TRANSFER_ENCODING' => 'chunked',
            'HTTP_HOST' => 'localhost',
        ];
        try {
            Request::capture();
            self::fail('expected BadRequestException');
        } catch (BadRequestException $e) {
            $this->assertSame('http.request.smuggling', $e->messageId);
        } finally {
            $_SERVER = $backup;
        }
    }

    /** bearer token */
    public function testBearerToken(): void
    {
        $r = Request::create('/x', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer abc123']);
        $this->assertSame('abc123', $r->bearerToken());

        $r2 = Request::create('/x');
        $this->assertNull($r2->bearerToken());
    }

    /** expectsJson / wantsJson */
    public function testExpectsJson(): void
    {
        $r = Request::create('/x', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertTrue($r->wantsJson());

        $api = Request::create('http://localhost/api/users', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*']);
        $this->assertTrue($api->expectsJson(), 'api/* paths expect json');
    }

    /** is() patterns */
    public function testPathPatterns(): void
    {
        $r = Request::create('/shop/products/5');
        $this->assertTrue($r->is('shop/*'));
        $this->assertTrue($r->is('shop/products/*'));
        $this->assertFalse($r->is('admin/*'));
    }

    /** url/fullUrl/host/scheme */
    public function testUrlParts(): void
    {
        $r = Request::create('https://example.com:8443/path?a=1', 'GET');
        $this->assertSame('https', $r->scheme());
        $this->assertSame('example.com', $r->host());
        $this->assertSame(8443, $r->port());
        $this->assertSame('/path', $r->path());
        $this->assertTrue($r->isSecure());
        $this->assertStringContainsString('a=1', $r->fullUrl());
    }

    /** casts */
    public function testInputCasts(): void
    {
        $r = Request::create('/x?n=42&f=1.5&b=1&s=x', 'GET');
        $this->assertSame(42, $r->integer('n'));
        $this->assertSame(1.5, $r->float('f'));
        $this->assertTrue($r->boolean('b'));
        $this->assertSame('x', $r->string('s'));
        $this->assertSame(99, $r->integer('missing', 99));
    }

    /** only/except/has/filled/missing */
    public function testInputHelpers(): void
    {
        $r = Request::create('/x?a=1&b=2&c=', 'GET');
        $this->assertSame(['a' => '1', 'b' => '2'], $r->only(['a', 'b']));
        $this->assertArrayNotHasKey('a', $r->except(['a']));
        $this->assertTrue($r->has('a'));
        $this->assertTrue($r->filled('a'));
        $this->assertFalse($r->filled('c'));
        $this->assertTrue($r->missing('zzz'));
    }

    /** route parameters */
    public function testRouteParameters(): void
    {
        $r = Request::create('/x');
        $r2 = $r->withRoute(new \stdClass(), ['id' => '7', 'slug' => 's']);
        $this->assertSame('7', $r2->routeParameter('id'));
        $this->assertSame(['id' => '7', 'slug' => 's'], $r2->routeParameters());
    }

    /** cookies */
    public function testCookies(): void
    {
        $r = Request::create('/x', 'GET', [], ['theme' => 'dark']);
        $this->assertSame('dark', $r->cookie('theme'));
        $this->assertNull($r->cookie('nope'));
    }

    /** control chars sanitization */
    public function testInputSanitized(): void
    {
        $r = Request::create('/x', 'GET', ['evil' => "a\x00b\x07c"]);
        $this->assertSame('abc', $r->query('evil'));
    }
}

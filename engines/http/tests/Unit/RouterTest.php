<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Tests\Unit;

use Tavaan\Core\Exceptions\Http\MethodNotAllowedException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Engine\Container\Container;
use Tavaan\Engine\Http\Routing\Exceptions\RouteCompilationException;
use Tavaan\Engine\Http\Routing\Exceptions\UrlGenerationException;
use Tavaan\Engine\Http\Routing\Route;
use Tavaan\Engine\Http\Routing\Router;
use Tavaan\Engine\Http\Routing\UrlGenerator;
use Tavaan\Testing\TestCase;
use Tests\Fixtures\Http\InvokableController;
use Tests\Fixtures\Http\TestController;

/**
 * تست موتور Router — سناریوهای DOC-040 §14 (AC-RT-07).
 */
final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router(new Container());
    }

    /** T-01: Static match با/بدون trailing slash */
    public function testStaticMatchAndTrailingSlash(): void
    {
        $this->router->get('/shop/products', [TestController::class, 'index'])->name('products.index');

        $m = $this->router->matchUri('GET', '/shop/products');
        $this->assertSame('products.index', $m->route->getName());

        $m2 = $this->router->matchUri('GET', '/shop/products/');
        $this->assertSame('products.index', $m2->route->getName());

        $m3 = $this->router->matchUri('HEAD', '/shop/products');
        $this->assertSame('products.index', $m3->route->getName(), 'HEAD accompanies GET');
    }

    /** T-02: Param با constraint */
    public function testParametricMatchWithConstraint(): void
    {
        $this->router->get('/users/{user}', [TestController::class, 'show'])->whereNumber('user');

        $m = $this->router->matchUri('GET', '/users/42');
        $this->assertSame('42', $m->parameters['user']);

        $this->expectException(RouteNotFoundException::class);
        $this->router->matchUri('GET', '/users/abc');
    }

    /** inline regex در uri */
    public function testInlineRegexInUri(): void
    {
        $this->router->get('/blog/{year:\d{4}}/{slug}', [TestController::class, 'show']);
        $m = $this->router->matchUri('GET', '/blog/1405/hello-world');
        $this->assertSame('1405', $m->parameters['year']);
        $this->assertSame('hello-world', $m->parameters['slug']);

        $this->expectException(RouteNotFoundException::class);
        $this->router->matchUri('GET', '/blog/14/hello');
    }

    /** T-03: اولویت static > param > wildcard */
    public function testStaticBeatsParamBeatsWildcard(): void
    {
        $this->router->get('/files/{path}', [TestController::class, 'index'])->where('path', '.*')->name('wild');
        $this->router->get('/files/{name}', [TestController::class, 'show'])->name('param');
        $this->router->get('/files/special', [TestController::class, 'staticAction'])->name('static');

        $this->assertSame('static', $this->router->matchUri('GET', '/files/special')->route->getName());
        $this->assertSame('param', $this->router->matchUri('GET', '/files/other')->route->getName());
        $this->assertSame('wild', $this->router->matchUri('GET', '/files/a/b/c')->route->getName());
    }

    /** T-04: Method mismatch → 405 با Allow */
    public function testMethodNotAllowedWithAllowHeader(): void
    {
        $this->router->get('/only-get', [TestController::class, 'index']);
        $this->router->post('/only-get', [TestController::class, 'index']);

        try {
            $this->router->matchUri('DELETE', '/only-get');
            self::fail('expected 405');
        } catch (MethodNotAllowedException $e) {
            $this->assertSame(405, $e->statusCode);
            $this->assertStringContainsString('GET', $e->headers['Allow']);
            $this->assertStringContainsString('POST', $e->headers['Allow']);
        }
    }

    /** T-05: Group prefix + name + middleware merge */
    public function testGroupMerging(): void
    {
        $this->router->prefix('api/v1')->name('api.v1.')->middleware(['api', 'throttle:60'])->group(function (): void {
            $this->router->get('/users', [TestController::class, 'index'])->name('users');
        });

        $m = $this->router->matchUri('GET', '/api/v1/users');
        $this->assertSame('api.v1.users', $m->route->getName());
        $this->assertSame(['api', 'throttle:60'], $m->route->getMiddleware());
    }

    /** nested groups */
    public function testNestedGroups(): void
    {
        $this->router->prefix('a')->name('a.')->group(function (): void {
            $this->router->prefix('b')->name('b.')->group(function (): void {
                $this->router->get('/c', [TestController::class, 'index'])->name('c');
            });
        });

        $m = $this->router->matchUri('GET', '/a/b/c');
        $this->assertSame('a.b.c', $m->route->getName());
    }

    /** T-06: Domain routing */
    public function testDomainRouting(): void
    {
        $this->router->domain('{tenant}.example.com')->get('/panel', [TestController::class, 'index'])->name('panel');
        $this->router->get('/panel', [TestController::class, 'staticAction'])->name('panel.main');

        $m = $this->router->matchUri('GET', '/panel', 'acme.example.com');
        $this->assertSame('panel', $m->route->getName());

        $m2 = $this->router->matchUri('GET', '/panel', 'other.host');
        $this->assertSame('panel.main', $m2->route->getName());
    }

    /** T-07: apiResource → ۵ روت */
    public function testApiResourceRegistersFiveRoutes(): void
    {
        $this->router->apiResource('photos', TestController::class);

        $this->assertTrue($this->router->has('photos.index'));
        $this->assertTrue($this->router->has('photos.store'));
        $this->assertTrue($this->router->has('photos.show'));
        $this->assertTrue($this->router->has('photos.update'));
        $this->assertTrue($this->router->has('photos.destroy'));
        $this->assertFalse($this->router->has('photos.create'));
        $this->assertFalse($this->router->has('photos.edit'));

        $m = $this->router->matchUri('GET', '/photos/9');
        $this->assertSame('photos.show', $m->route->getName());
        $this->assertSame('9', $m->parameters['photo']);
    }

    /** resource کامل → ۷ روت */
    public function testFullResourceRegistersSevenRoutes(): void
    {
        $this->router->resource('posts', TestController::class);
        foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'] as $action) {
            $this->assertTrue($this->router->has('posts.' . $action), "posts.{$action} must exist");
        }
    }

    /** T-12: Route cache roundtrip */
    public function testCacheRoundtrip(): void
    {
        $this->router->get('/cached/{id}', [TestController::class, 'show'])->whereNumber('id')->name('cached')->middleware('web');

        $data = $this->router->toCacheArray();
        $restored = new Router(new Container());
        $restored->loadCached($data);

        $m = $restored->matchUri('GET', '/cached/7');
        $this->assertSame('cached', $m->route->getName());
        $this->assertSame(['web'], $m->route->getMiddleware());
        $this->assertSame('7', $m->parameters['id']);
    }

    /** closure روت → cache ناممکن */
    public function testClosureRoutesCannotBeCached(): void
    {
        $this->router->get('/closure', static fn (): string => 'x');
        $this->expectException(\LogicException::class);
        $this->router->toCacheArray();
    }

    /** T-14: ReDoS regex → رد */
    public function testRedosRegexRejected(): void
    {
        $this->router->get('/bad/{x}', [TestController::class, 'index'])->where('x', '(a+)+');
        $this->expectException(RouteCompilationException::class);
        $this->router->matchUri('GET', '/bad/aaa');
    }

    /** T-15: UrlGenerator — پارامتر اضافه → query */
    public function testUrlGenerationWithExtraParams(): void
    {
        $this->router->get('/p/{id}', [TestController::class, 'show'])->whereNumber('id')->name('p.show');

        $gen = new UrlGenerator($this->router, null, 'http://localhost');
        $url = $gen->route('p.show', ['id' => 5, 'page' => 2, 'sort' => 'new'], absolute: false);
        $this->assertSame('/p/5?page=2&sort=new', $url);

        $abs = $gen->route('p.show', ['id' => 5]);
        $this->assertSame('http://localhost/p/5', $abs);
    }

    public function testUrlGenerationMissingParamThrows(): void
    {
        $this->router->get('/p/{id}', [TestController::class, 'show'])->name('p2.show');
        $gen = new UrlGenerator($this->router, null, 'http://localhost');
        $this->expectException(UrlGenerationException::class);
        $gen->route('p2.show');
    }

    /** T-16: Signed URL با expires منقضی → invalid */
    public function testSignedUrlExpired(): void
    {
        $this->router->get('/verify/{id}', [TestController::class, 'show'])->name('verify');

        $crypt = new \Tavaan\Engine\Crypt\Crypt(new \Tavaan\Engine\Crypt\KeyRing(\Tavaan\Engine\Crypt\KeyRing::generate()));
        $gen = new UrlGenerator($this->router, $crypt, 'http://localhost');

        $future = $gen->temporarySignedRoute('verify', new \DateTimeImmutable('+1 hour'), ['id' => 3]);
        $past = $gen->temporarySignedRoute('verify', new \DateTimeImmutable('-1 hour'), ['id' => 3]);

        $reqFuture = \Tavaan\Engine\Http\Request::create($future);
        $reqPast = \Tavaan\Engine\Http\Request::create($past);
        $gen->setRequestContext($reqFuture);

        $this->assertTrue($gen->isValidSignature($reqFuture));
        $this->assertFalse($gen->isValidSignature($reqPast));

        // tamper
        $tampered = \Tavaan\Engine\Http\Request::create($future . '&id=999');
        $this->assertFalse($gen->isValidSignature($tampered));
    }

    /** T-17: Fallback */
    public function testFallbackRoute(): void
    {
        $this->router->get('/known', [TestController::class, 'index']);
        $this->router->fallback([TestController::class, 'staticAction']);

        $this->assertSame('/known', $this->router->matchUri('GET', '/known')->route->uri());
        $m = $this->router->matchUri('GET', '/anything/else/deep');
        $this->assertStringContainsString('fallbackPlaceholder', $m->route->uri());
    }

    /** where helpers */
    public function testWhereHelpers(): void
    {
        $this->router->get('/u/{id}', [TestController::class, 'show'])->whereUuid('id');
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->assertSame($uuid, $this->router->matchUri('GET', '/u/' . $uuid)->parameters['id']);

        $this->expectException(RouteNotFoundException::class);
        $this->router->matchUri('GET', '/u/not-a-uuid');
    }

    /** defaults */
    public function testRouteDefaults(): void
    {
        $this->router->get('/page/{num?}', [TestController::class, 'show'])->defaults('num', '1');
        // پارامتر اختیاری: /page بدون num
        $this->router->get('/page', [TestController::class, 'index'])->name('page.first');

        $m = $this->router->matchUri('GET', '/page');
        $this->assertSame('page.first', $m->route->getName());
    }

    /** unicode params */
    public function testUnicodeParameter(): void
    {
        $this->router->get('/hello/{name}', [TestController::class, 'show'])->where('name', '[\p{L}\p{N}\-_]+');
        $m = $this->router->matchUri('GET', '/hello/دنیای-من');
        $this->assertSame('دنیای-من', $m->parameters['name']);
    }

    /** any/match */
    public function testAnyAndMatch(): void
    {
        $this->router->any('/any', [TestController::class, 'index']);
        foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
            $this->assertSame('/any', $this->router->matchUri($method, '/any')->route->uri());
        }

        $this->router->match(['PATCH'], '/patch-only', [TestController::class, 'index']);
        $this->assertSame('/patch-only', $this->router->matchUri('PATCH', '/patch-only')->route->uri());
    }

    /**
     * Regression: گره با ≥۳ فرزند static — بهینه‌سازی حافظهٔ TrieNode
     * (تک‌فرزند inline + مهاجرت به map) نباید فرزندی را پنهان کند.
     */
    public function testManyStaticChildrenAtSameLevel(): void
    {
        $this->router->get('/x/one', [TestController::class, 'index'])->name('x.one');
        $this->router->get('/x/two', [TestController::class, 'index'])->name('x.two');
        $this->router->get('/x/three', [TestController::class, 'index'])->name('x.three');

        $this->assertSame('x.one', $this->router->matchUri('GET', '/x/one')->route->getName());
        $this->assertSame('x.two', $this->router->matchUri('GET', '/x/two')->route->getName());
        $this->assertSame('x.three', $this->router->matchUri('GET', '/x/three')->route->getName());

        // فرزند چهارم/پنجم — پس از مهاجرت single→map
        $this->router->get('/x/four', [TestController::class, 'index'])->name('x.four');
        $this->router->get('/x/five', [TestController::class, 'index'])->name('x.five');
        $this->assertSame('x.four', $this->router->matchUri('GET', '/x/four')->route->getName());
        $this->assertSame('x.five', $this->router->matchUri('GET', '/x/five')->route->getName());

        // همهٔ فرزندان قبلی هم هنوز قابل match باشند
        $this->assertSame('x.one', $this->router->matchUri('GET', '/x/one')->route->getName());
        $this->assertSame('x.three', $this->router->matchUri('GET', '/x/three')->route->getName());
    }

    /** Regression: همزیستی فرزند static و یال پارامتریک در یک گره. */
    public function testStaticAndParamSiblings(): void
    {
        $this->router->get('/d/static', [TestController::class, 'index'])->name('d.static');
        $this->router->get('/d/{id}', [TestController::class, 'index'])->whereNumber('id')->name('d.param');

        $this->assertSame('d.static', $this->router->matchUri('GET', '/d/static')->route->getName());
        $m = $this->router->matchUri('GET', '/d/42');
        $this->assertSame('d.param', $m->route->getName());
        $this->assertSame('42', $m->parameters['id']);
    }
}

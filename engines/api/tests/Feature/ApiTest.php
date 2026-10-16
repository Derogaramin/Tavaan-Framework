<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Tests\Feature;

require_once __DIR__ . '/../Fixtures/ApiFixtures.php';

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Api\Middleware\ApiVersionMiddleware;
use Tavaan\Engine\Api\Middleware\IdempotencyMiddleware;
use Tavaan\Engine\Api\Middleware\ThrottleMiddleware;
use Tavaan\Engine\Api\OpenApi\OpenApiGenerator;
use Tavaan\Engine\Api\OpenApi\Yaml;
use Tavaan\Engine\Api\Resources\JsonResource;
use Tavaan\Engine\Api\Resources\MissingValue;
use Tavaan\Engine\Api\Tests\Fixtures\CategoryResource;
use Tavaan\Engine\Api\Tests\Fixtures\FakeProduct;
use Tavaan\Engine\Api\Tests\Fixtures\ProductResource;
use Tavaan\Engine\Api\Tests\Fixtures\TestProductController;
use Tavaan\Engine\Database\Pagination\Paginator;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Http\Routing\Router;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور API — DOC-044 §10 (T-01..T-07).
 */
final class ApiTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        Clock::setTestNow(null);
        $this->app = \tavaan_test_app();
        $this->app->make('config')->set('log.default', 'array');
        JsonResource::$wrap = 'data';
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
        JsonResource::$wrap = 'data';
    }

    private function product(bool $withCategory = false, bool $withCounts = false): FakeProduct
    {
        $p = new FakeProduct(['id' => 7, 'title' => 'لپ تاپ', 'price' => 1000, 'stock' => 3]);
        if ($withCategory) {
            $p->setRelationValue('category', new FakeProduct(['id' => 2, 'title' => 'دیجیتال']));
        }
        if ($withCounts) {
            $p->comments_count = 4;
        }

        return $p;
    }

    // ─── T-01: Resource با nested + whenLoaded ────────────────────────────

    public function testResourceResolvesWithDataWrap(): void
    {
        $resource = ProductResource::make($this->product());
        $resolved = $resource->resolve();

        $this->assertSame(7, $resolved['id']);
        $this->assertSame('لپ تاپ', $resolved['title']);
        // whenLoaded بدون بارگذاری → کلید حذف می‌شود
        $this->assertFalse(\array_key_exists('category', $resolved));

        $json = \json_encode($resource, JSON_UNESCAPED_UNICODE);
        $decoded = \json_decode((string) $json, true);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertSame(7, $decoded['data']['id']);
    }

    public function testWhenLoadedIncludesNestedResource(): void
    {
        $resource = ProductResource::make($this->product(withCategory: true));
        $resolved = $resource->resolve();

        $this->assertArrayHasKey('category', $resolved);
        $this->assertSame(2, $resolved['category']['id']);
        $this->assertSame('دیجیتال', $resolved['category']['title']);
    }

    public function testWhenHidesFieldForGuest(): void
    {
        // T-07: فیلد شرطی برای مهمان پنهان است
        $resource = ProductResource::make($this->product());
        $resolved = $resource->resolve();
        $this->assertFalse(\array_key_exists('in_stock', $resolved));

        // با attribute کاربر روی request → فیلد حاضر
        $request = Request::create('http://localhost/api/v1/products/7')->withAttribute('user', new \stdClass());
        $resolved2 = ProductResource::make($this->product())->withRequest($request)->resolve();
        $this->assertTrue($resolved2['in_stock']);
    }

    public function testWhenClosureIsLazy(): void
    {
        ProductResource::$lazyProbeCalls = 0;
        $resource = ProductResource::make($this->product());
        $resolved = $resource->resolve();

        // شرط false → closure نباید اجرا شود
        $this->assertSame(0, ProductResource::$lazyProbeCalls);
        $this->assertFalse(\array_key_exists('lazy', $resolved));
    }

    public function testWhenCounted(): void
    {
        $without = ProductResource::make($this->product())->resolve();
        $this->assertFalse(\array_key_exists('comments_count', $without));

        $with = ProductResource::make($this->product(withCounts: true))->resolve();
        $this->assertSame(4, $with['comments_count']);
    }

    public function testMergeWhenMergesKeysIntoParent(): void
    {
        $resource = ProductResource::make($this->product());

        // شرط mergeWhen در fixture بر اساس stock > 0 است
        $resolved = $resource->resolve();
        $this->assertSame(1000, $resolved['price']);
        $this->assertSame('IRT', $resolved['currency']);      // ادغام‌شده از mergeWhen
        $this->assertFalse(\array_key_exists('internal', $resolved));
    }

    public function testWhenHasWithEmptyRelation(): void
    {
        $p = $this->product();
        $p->setRelationValue('tags', []);
        $resolved = ProductResource::make($p)->resolve();
        $this->assertFalse(\array_key_exists('has_tags', $resolved));

        $p2 = $this->product();
        $p2->setRelationValue('tags', ['a']);
        $resolved2 = ProductResource::make($p2)->resolve();
        $this->assertTrue($resolved2['has_tags']);
    }

    public function testMissingValueNeverLeaksIntoOutput(): void
    {
        $resource = ProductResource::make($this->product());
        $resolved = $resource->resolve();
        $json = (string) \json_encode($resolved, JSON_UNESCAPED_UNICODE);
        $this->assertFalse(\str_contains($json, 'MissingValue'));
        $this->assertFalse(\in_array(MissingValue::instance(), $resolved, true));
    }

    // ─── T-02: Collection با pagination meta ─────────────────────────────

    public function testCollectionFromPlainArray(): void
    {
        $collection = ProductResource::collection([
            $this->product(),
            new FakeProduct(['id' => 8, 'title' => 'موبایل', 'price' => 500, 'stock' => 0]),
        ]);

        $decoded = \json_decode((string) \json_encode($collection, JSON_UNESCAPED_UNICODE), true);
        $this->assertCount(2, $decoded['data']);
        $this->assertSame(7, $decoded['data'][0]['id']);
        $this->assertSame(8, $decoded['data'][1]['id']);
        $this->assertFalse(\array_key_exists('meta', $decoded));
    }

    public function testCollectionWithPaginatorEmitsMetaAndLinks(): void
    {
        $items = [];
        for ($i = 1; $i <= 3; $i++) {
            $items[] = new FakeProduct(['id' => $i, 'title' => 'p' . $i, 'price' => 10 * $i, 'stock' => 1]);
        }
        $paginator = new Paginator($items, total: 7, perPage: 3, currentPage: 2);

        $collection = ProductResource::collection($paginator);
        $decoded = \json_decode((string) \json_encode($collection, JSON_UNESCAPED_UNICODE), true);

        $this->assertArrayHasKey('data', $decoded);
        $this->assertCount(3, $decoded['data']);
        $this->assertArrayHasKey('meta', $decoded);
        $this->assertSame(7, $decoded['meta']['total']);
        $this->assertSame(2, $decoded['meta']['current_page']);
        $this->assertSame(3, $decoded['meta']['last_page']);
        $this->assertArrayHasKey('links', $decoded);
    }

    public function testResourceResponseMethod(): void
    {
        $response = ProductResource::make($this->product())->response();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('application/json', (string) $response->header('Content-Type'));

        $decoded = \json_decode($response->body(), true);
        $this->assertSame(7, $decoded['data']['id']);
    }

    public function testWrapCanBeDisabled(): void
    {
        JsonResource::$wrap = null;
        $decoded = \json_decode((string) \json_encode(ProductResource::make($this->product()), JSON_UNESCAPED_UNICODE), true);
        $this->assertSame(7, $decoded['id']);
        $this->assertFalse(\array_key_exists('data', $decoded));
    }

    public function testControllerReturningResourceIsJsonResponse(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $router->get('/api/v1/products/7', static fn (): ProductResource => ProductResource::make(
            new FakeProduct(['id' => 7, 'title' => 'x', 'price' => 1, 'stock' => 1]),
        ))->name('api.v1.products.show');

        $kernel = $this->app->make(\Tavaan\Engine\Http\HttpKernel::class);
        $response = $kernel->handle(Request::create('http://localhost/api/v1/products/7'));

        $this->assertSame(200, $response->statusCode());
        $decoded = \json_decode($response->body(), true);
        $this->assertSame(7, $decoded['data']['id']);
    }

    // ─── T-03: Versioning url/header ─────────────────────────────────────

    private function versionMiddleware(array $overrides = []): ApiVersionMiddleware
    {
        return new ApiVersionMiddleware(null, [
            'strategy' => 'url',
            'default' => 'v1',
            'prefix' => 'application/vnd.tavaan.',
            'supported' => ['v1', 'v2'],
            'deprecated' => ['v1'],
            'sunset' => ['v1' => 'Sat, 01 Jan 2028 00:00:00 GMT'],
            ...$overrides,
        ]);
    }

    private function ok(): \Closure
    {
        return static fn (Request $r): Response => Response::json(['ok' => true]);
    }

    public function testVersionFromUrl(): void
    {
        $mw = $this->versionMiddleware();
        $request = Request::create('http://localhost/api/v2/products');
        /** @var Response $response */
        $response = $mw->handle($request, $this->ok());
        $this->assertSame('v2', $response->header('X-Api-Version'));
    }

    public function testVersionFromAcceptHeader(): void
    {
        $mw = $this->versionMiddleware(['strategy' => 'header']);
        $request = Request::create('http://localhost/products', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.tavaan.v2+json',
        ]);
        /** @var Response $response */
        $response = $mw->handle($request, $this->ok());
        $this->assertSame('v2', $response->header('X-Api-Version'));
    }

    public function testVersionFromQuery(): void
    {
        $mw = $this->versionMiddleware(['strategy' => 'query']);
        $request = Request::create('http://localhost/products?version=2');
        /** @var Response $response */
        $response = $mw->handle($request, $this->ok());
        $this->assertSame('v2', $response->header('X-Api-Version'));
    }

    public function testExplicitMiddlewareParamWins(): void
    {
        $mw = $this->versionMiddleware();
        $request = Request::create('http://localhost/api/v1/products');
        /** @var Response $response */
        $response = $mw->handle($request, $this->ok(), '2');
        $this->assertSame('v2', $response->header('X-Api-Version'));
    }

    public function testUnsupportedVersionReturns404Problem(): void
    {
        $mw = $this->versionMiddleware();
        $request = Request::create('http://localhost/api/v9/products');
        /** @var Response $response */
        $response = $mw->handle($request, $this->ok());
        $this->assertSame(404, $response->statusCode());
        $this->assertStringContainsString('application/problem+json', (string) $response->header('Content-Type'));
        $decoded = \json_decode($response->body(), true);
        $this->assertSame(404, $decoded['status']);
    }

    public function testDeprecatedVersionGetsDeprecationAndSunsetHeaders(): void
    {
        $mw = $this->versionMiddleware();
        $request = Request::create('http://localhost/api/v1/products');
        /** @var Response $response */
        $response = $mw->handle($request, $this->ok());

        $this->assertSame('v1', $response->header('X-Api-Version'));
        $this->assertSame('true', $response->header('Deprecation'));
        $this->assertSame('Sat, 01 Jan 2028 00:00:00 GMT', $response->header('Sunset'));
    }

    // ─── T-05: Idempotency ───────────────────────────────────────────────

    public function testIdempotencyReplaysSameResponse(): void
    {
        $cache = $this->app->make('cache')->store('array');
        $mw = new IdempotencyMiddleware($cache, 600);

        $calls = 0;
        $handler = static function (Request $r) use (&$calls): Response {
            $calls++;

            return Response::json(['order_id' => 101], 201);
        };

        $request = Request::create('http://localhost/api/v1/orders', 'POST', ['x' => 1], [], [], ['HTTP_IDEMPOTENCY_KEY' => 'key-abc']);

        $first = $mw->handle($request, $handler);
        $second = $mw->handle($request, $handler);

        $this->assertSame(1, $calls);                       // 🔒 یک اثر
        $this->assertSame(201, $first->statusCode());
        $this->assertSame(201, $second->statusCode());
        $this->assertSame($first->body(), $second->body());
        $this->assertSame('true', $second->header('X-Idempotent-Replay'));
        $this->assertNull($first->header('X-Idempotent-Replay'));
    }

    public function testIdempotencyWithoutKeyPassesThrough(): void
    {
        $cache = $this->app->make('cache')->store('array');
        $mw = new IdempotencyMiddleware($cache, 600);

        $calls = 0;
        $handler = static function (Request $r) use (&$calls): Response {
            $calls++;

            return Response::json(['n' => $calls]);
        };

        $request = Request::create('http://localhost/api/v1/orders', 'POST');
        $mw->handle($request, $handler);
        $mw->handle($request, $handler);
        $this->assertSame(2, $calls);
    }

    public function testIdempotencyDifferentKeysAreIndependent(): void
    {
        $cache = $this->app->make('cache')->store('array');
        $mw = new IdempotencyMiddleware($cache, 600);

        $calls = 0;
        $handler = static function (Request $r) use (&$calls): Response {
            $calls++;

            return Response::json(['call' => $calls]);
        };

        $r1 = Request::create('http://localhost/o', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => 'k1']);
        $r2 = Request::create('http://localhost/o', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => 'k2']);

        $a = $mw->handle($r1, $handler);
        $b = $mw->handle($r2, $handler);
        $this->assertSame(2, $calls);
        $this->assertNotSame($a->body(), $b->body());
    }

    public function testIdempotencyInProgressReturns409(): void
    {
        $cache = $this->app->make('cache')->store('array');
        $mw = new IdempotencyMiddleware($cache, 600);

        // قفل از قبل وجود دارد (درخواست موازی در جریان)
        $cache->add('idempotency:' . \sha1('api/v1/orders|key-xyz') . ':lock', 1, 600);

        $request = Request::create('http://localhost/api/v1/orders', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => 'key-xyz']);
        /** @var Response $response */
        $response = $mw->handle($request, static fn (): Response => Response::json([]));
        $this->assertSame(409, $response->statusCode());
    }

    public function testServerErrorResponsesAreNotCached(): void
    {
        $cache = $this->app->make('cache')->store('array');
        $mw = new IdempotencyMiddleware($cache, 600);

        $calls = 0;
        $handler = static function (Request $r) use (&$calls): Response {
            $calls++;

            return $calls === 1 ? new Response('boom', 500) : Response::json(['ok' => true]);
        };

        $request = Request::create('http://localhost/o', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => 'k5']);
        $first = $mw->handle($request, $handler);
        $second = $mw->handle($request, $handler);

        $this->assertSame(500, $first->statusCode());
        $this->assertSame(200, $second->statusCode());   // 🔒 خطای ۵۰۰ replay نشد
        $this->assertSame(2, $calls);
    }

    // ─── T-06: Rate limit ────────────────────────────────────────────────

    public function testThrottleNumericLimiter(): void
    {
        $mw = new ThrottleMiddleware($this->app);
        // throttle:3,1 → ۳ درخواست در دقیقه
        $request = Request::create('http://localhost/api/v1/x');
        $handler = static fn (): Response => Response::json(['ok' => true]);

        for ($i = 1; $i <= 3; $i++) {
            /** @var Response $response */
            $response = $mw->handle($request, $handler, '3', '1');
            $this->assertSame(200, $response->statusCode());
            $this->assertSame('3', $response->header('X-RateLimit-Limit'));
            $this->assertSame((string) (3 - $i), $response->header('X-RateLimit-Remaining'));
        }

        /** @var Response $blocked */
        $blocked = $mw->handle($request, $handler, '3', '1');
        $this->assertSame(429, $blocked->statusCode());
        $this->assertSame('0', $blocked->header('X-RateLimit-Remaining'));
        $this->assertNotNull($blocked->header('Retry-After'));
        $this->assertStringContainsString('application/problem+json', (string) $blocked->header('Content-Type'));
    }

    public function testThrottleNamedLimiterFromConfig(): void
    {
        $this->app->make('config')->set('api.rate_limit.small', ['max' => 2, 'decay' => 60]);
        $mw = new ThrottleMiddleware($this->app);

        $request = Request::create('http://localhost/api/v1/x');
        $handler = static fn (): Response => Response::json([]);

        $mw->handle($request, $handler, 'small');
        $mw->handle($request, $handler, 'small');
        /** @var Response $third */
        $third = $mw->handle($request, $handler, 'small');
        $this->assertSame(429, $third->statusCode());
    }

    public function testThrottleWindowResetsAfterDecay(): void
    {
        $mw = new ThrottleMiddleware($this->app);
        $request = Request::create('http://localhost/api/v1/x');
        $handler = static fn (): Response => Response::json([]);

        Clock::setTestNow('2026-06-01 10:00:00');
        $mw->handle($request, $handler, '2', '1');
        $mw->handle($request, $handler, '2', '1');
        /** @var Response $blocked */
        $blocked = $mw->handle($request, $handler, '2', '1');
        $this->assertSame(429, $blocked->statusCode());

        // ۶۱ ثانیه بعد — پنجره تازه
        Clock::setTestNow('2026-06-01 10:01:01');
        /** @var Response $ok */
        $ok = $mw->handle($request, $handler, '2', '1');
        $this->assertSame(200, $ok->statusCode());
    }

    public function testThrottleSeparatesIdentitiesByApiKey(): void
    {
        $mw = new ThrottleMiddleware($this->app);
        $handler = static fn (): Response => Response::json([]);

        $r1 = Request::create('http://localhost/x', 'GET', [], [], [], ['HTTP_X_API_KEY' => 'key-1']);
        $r2 = Request::create('http://localhost/x', 'GET', [], [], [], ['HTTP_X_API_KEY' => 'key-2']);

        $mw->handle($r1, $handler, '1', '1');
        /** @var Response $blocked1 */
        $blocked1 = $mw->handle($r1, $handler, '1', '1');
        $this->assertSame(429, $blocked1->statusCode());

        // کلید دیگر هنوز سهمیه دارد
        /** @var Response $ok2 */
        $ok2 = $mw->handle($r2, $handler, '1', '1');
        $this->assertSame(200, $ok2->statusCode());
    }

    // ─── T-04: OpenAPI generation ────────────────────────────────────────

    private function generatorWithRoutes(): OpenApiGenerator
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $router->get('/api/v1/products', [TestProductController::class, 'index'])->name('api.v1.products.index');
        $router->post('/api/v1/products', [TestProductController::class, 'store'])->name('api.v1.products.store')->middleware('auth');
        $router->get('/api/v1/products/{product}', [TestProductController::class, 'show'])
            ->name('api.v1.products.show')->whereNumber('product');
        $router->get('/api/v2/products', [TestProductController::class, 'index'])->name('api.v2.products.index');

        return new OpenApiGenerator($router, [
            'prefix' => 'api',
            'openapi' => ['title' => 'Test API', 'version' => '2.3.4', 'servers' => [['url' => 'https://example.com/api/v1']]],
        ]);
    }

    public function testOpenApiGeneratesPathsForVersion(): void
    {
        $generator = $this->generatorWithRoutes();
        $spec = $generator->generate('v1');

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertSame('Test API', $spec['info']['title']);
        $this->assertSame('2.3.4', $spec['info']['version']);

        // فقط مسیرهای v1 (نه v2)
        $this->assertArrayHasKey('/products', $spec['paths']);
        $this->assertArrayHasKey('/products/{product}', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/products']);
        $this->assertArrayHasKey('post', $spec['paths']['/products']);
        $this->assertFalse(isset($spec['paths']['/api/v2/products']));
    }

    public function testOpenApiOperationMetadata(): void
    {
        $spec = $this->generatorWithRoutes()->generate('v1');
        $index = $spec['paths']['/products']['get'];

        $this->assertSame('api.v1.products.index', $index['operationId']);
        $this->assertSame(['products'], $index['tags']);
        $this->assertSame('فهرست محصولات', $index['summary']);

        // #[ApiParam] کوئری
        $names = \array_column($index['parameters'], 'name');
        $this->assertContains('page', $names);
        $page = null;
        foreach ($index['parameters'] as $p) {
            if ($p['name'] === 'page') {
                $page = $p;
            }
        }
        $this->assertSame('query', $page['in']);
        $this->assertSame('integer', $page['schema']['type']);

        // پاسخ‌ها از attribute
        $this->assertArrayHasKey('200', $index['responses']);
        $this->assertSame('موفق', $index['responses']['200']['description']);
        $this->assertArrayHasKey('422', $index['responses']);
    }

    public function testOpenApiPathParameterTypeFromWheres(): void
    {
        $spec = $this->generatorWithRoutes()->generate('v1');
        $show = $spec['paths']['/products/{product}']['get'];

        $param = $show['parameters'][0];
        $this->assertSame('product', $param['name']);
        $this->assertSame('path', $param['in']);
        $this->assertTrue($param['required']);
        $this->assertSame('integer', $param['schema']['type']);   // whereNumber
    }

    public function testOpenApiRequestBodyFromFormRequest(): void
    {
        $spec = $this->generatorWithRoutes()->generate('v1');
        $store = $spec['paths']['/products']['post'];

        $this->assertArrayHasKey('requestBody', $store);
        $schema = $store['requestBody']['content']['application/json']['schema'];
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('title', $schema['properties']);
        $this->assertSame('string', $schema['properties']['title']['type']);
        $this->assertSame(200, $schema['properties']['title']['maxLength']);
        $this->assertSame('number', $schema['properties']['price']['type']);
        $this->assertSame('boolean', $schema['properties']['active']['type']);
        $this->assertContains('title', $schema['required']);
        $this->assertContains('price', $schema['required']);
    }

    public function testOpenApiSecurityFromAuthMiddleware(): void
    {
        $spec = $this->generatorWithRoutes()->generate('v1');

        $this->assertSame([['bearerAuth' => []]], $spec['paths']['/products']['post']['security']);
        $this->assertFalse(isset($spec['paths']['/products']['get']['security']));
        $this->assertSame('http', $spec['components']['securitySchemes']['bearerAuth']['type']);
        $this->assertSame('bearer', $spec['components']['securitySchemes']['bearerAuth']['scheme']);
    }

    public function testOpenApiJsonAndYamlOutput(): void
    {
        $generator = $this->generatorWithRoutes();
        $spec = $generator->generate('v1');

        $json = $generator->toJson($spec);
        $decoded = \json_decode($json, true);
        $this->assertNotNull($decoded);
        $this->assertSame('3.1.0', $decoded['openapi']);

        $yaml = $generator->toYaml($spec);
        $this->assertStringContainsString('openapi: 3.1.0', $yaml);
        $this->assertStringContainsString('paths:', $yaml);
        $this->assertStringContainsString('/products:', $yaml);
        // رشتهٔ فارسی باید quote شود (کنترل کاراکتر خاص ندارد اما امن‌تر quote شود یا plain — هر دو معتبر)
        $this->assertStringContainsString('فهرست محصولات', $yaml);
        // مسیرها به‌عنوان کلید
        $this->assertStringContainsString('operationId: api.v1.products.index', $yaml);
    }

    public function testRoutesForVersionFiltering(): void
    {
        $generator = $this->generatorWithRoutes();
        $v1 = $generator->routesForVersion('v1', 'api');
        $v2 = $generator->routesForVersion('v2', 'api');

        // routes/api.php نمونه هم بارگذاری می‌شود — فقط روت‌های products این تست را می‌شماریم
        $v1Products = \array_filter($v1, static fn ($r): bool => \str_contains($r->uri(), 'products'));
        $this->assertCount(3, $v1Products);
        $this->assertCount(1, $v2);
        $this->assertStringContainsString('v2', $v2[0]->uri());
    }

    public function testConvertPathStripsVersionPrefix(): void
    {
        $generator = new OpenApiGenerator($this->app->make('router'), ['prefix' => 'api']);
        $this->assertSame('/products', $generator->convertPath('api/v1/products'));
        $this->assertSame('/products/{product}', $generator->convertPath('/api/v1/products/{product}'));
        $this->assertSame('/', $generator->convertPath('api/v1'));
    }

    public function testYamlEmitterBasics(): void
    {
        $yaml = Yaml::emit([
            'string' => 'hello',
            'number' => 42,
            'float' => 1.5,
            'bool' => true,
            'null' => null,
            'list' => [1, 2, 3],
            'nested' => ['a' => ['b' => 'c']],
            'objects' => [['id' => 1, 'name' => 'x'], ['id' => 2, 'name' => 'y']],
            'quoted' => 'has: colon',
            'numeric_string' => '123',
            'reserved' => 'true',
            'empty_map' => [],
            'slash_path' => '/products/{id}',
        ]);

        $this->assertStringContainsString('string: hello', $yaml);
        $this->assertStringContainsString('number: 42', $yaml);
        $this->assertStringContainsString('float: 1.5', $yaml);
        $this->assertStringContainsString('bool: true', $yaml);
        $this->assertStringContainsString('"null": null', $yaml);   // کلید رزرو quote می‌شود
        $this->assertStringContainsString("- 1\n", $yaml);
        $this->assertStringContainsString("a:\n", $yaml);
        $this->assertStringContainsString("b: c", $yaml);
        $this->assertStringContainsString('- id: 1', $yaml);
        $this->assertStringContainsString('  name: x', $yaml);
        $this->assertStringContainsString('quoted: "has: colon"', $yaml);
        $this->assertStringContainsString('numeric_string: "123"', $yaml);
        $this->assertStringContainsString('reserved: "true"', $yaml);
        $this->assertStringContainsString('empty_map: []', $yaml);
        // '{}' کاراکتر خاص است → quote می‌شود (یامل معتبر)
        $this->assertStringContainsString('slash_path: "/products/{id}"', $yaml);
    }

    // ─── aliasهای middleware ─────────────────────────────────────────────

    public function testMiddlewareAliasesRegistered(): void
    {
        $aliases = (array) $this->app->make('config')->get('http.middleware.aliases', []);
        $this->assertArrayHasKey('throttle', $aliases);
        $this->assertArrayHasKey('idempotent', $aliases);
        $this->assertArrayHasKey('api.version', $aliases);
    }

    public function testApiDocsCommandProducesYamlFile(): void
    {
        $router = $this->app->make('router');
        $router->get('/api/v1/items', static fn (): array => [])->name('api.v1.items.index');

        $relative = 'storage/tmp-api-test-' . \getmypid() . '/openapi.yaml';
        $absolute = TVN_BASE_PATH . '/' . $relative;

        /** @var \Tavaan\Engine\Console\ConsoleKernel $console */
        $console = $this->app->make(\Tavaan\Engine\Console\ConsoleKernel::class);
        $exit = $console->call('api:docs', ['--version' => 'v1', '--format' => 'yaml', '--output' => $relative]);

        $this->assertSame(0, $exit);
        $this->assertTrue(\is_file($absolute));

        $content = (string) \file_get_contents($absolute);
        $this->assertStringContainsString('openapi: 3.1.0', $content);
        $this->assertStringContainsString('/items:', $content);

        @\unlink($absolute);
        @\rmdir(\dirname($absolute));
    }

    public function testApiDocsCommandRejectsOutsidePaths(): void
    {
        /** @var \Tavaan\Engine\Console\ConsoleKernel $console */
        $console = $this->app->make(\Tavaan\Engine\Console\ConsoleKernel::class);
        $exit = $console->call('api:docs', ['--version' => 'v1', '--output' => '../evil.yaml']);
        $this->assertSame(1, $exit);
    }
}

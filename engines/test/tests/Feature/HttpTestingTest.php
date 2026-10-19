<?php

declare(strict_types=1);

namespace Tavaan\Testing\Tests\Feature;

require_once __DIR__ . '/../Fixtures/WidgetFixtures.php';

use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Engine\Auth\AuthManager;
use Tavaan\Engine\Http\Cookie\Cookie;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Http\Routing\Router;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;
use Tavaan\Testing\Tests\Fixtures\TestUserFixture;

/**
 * تست HttpTest درون‌فرایندی (DOC-084E §5 — AC-TEST-02).
 *
 * هر متد تست یک اپلیکیشن تازه می‌گیرد؛ روت‌های fixture در setUp ثبت می‌شوند.
 * session/log/database حافظه‌ای و CSRF برای مسیرهای تست معاف است
 * (بدون I/O واقعی — CON-11).
 */
final class HttpTestingTest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->setUpDatabase();   // sqlite_memory → هیچ فایل DB واقعی ساخته نمی‌شود

        $config = $this->app()->make('config');

        // 🔒 determinism بدون وابستگی به .env: کلید تست ثابت + debug روشن
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }
        $config->set('app.debug', true);
        $config->set('session.csrf.except', ['tvn-test/*']);

        $this->registerFixtureRoutes();
    }

    private function registerFixtureRoutes(): void
    {
        /** @var Router $router */
        $router = $this->app()->make(Router::class);

        $router->get('/tvn-test/ping', static fn (): Response => Response::json(['pong' => true, 'n' => 3]));

        $router->post('/tvn-test/echo', static fn (Request $request): Response => Response::json(
            ['received' => $request->json() ?: $request->post()],
            201,
        ));

        $router->get('/tvn-test/html', static fn (): Response => new Response('<p>سلام دنیا</p>'));

        $router->get('/tvn-test/go', static fn (): Response => (new Response('', 302))->withHeader('Location', '/login'));

        $router->get('/tvn-test/with-cookie', static fn (): Response => (new Response('ok'))->cookie(new Cookie('tvn_test_cookie', '1')));

        $router->get('/tvn-test/echo-header', static fn (Request $request): Response => Response::json([
            'x_test' => $request->header('X-Test'),
        ]));

        $router->get('/tvn-test/fail422', static function (): never {
            throw new ValidationException(errors: ['title' => ['title.required']]);
        });

        $router->get('/tvn-test/me', static fn (): Response => Response::json([
            'id' => app(AuthManager::class)->guard('web')->user()?->getAuthIdentifier(),
        ]));

        // مسیر معاف‌نشده از CSRF — برای تست 419
        $router->post('/tvn-csrf/protected', static fn (): Response => Response::json(['ok' => true]));
    }

    // ─── AC-TEST-02: درخواست کامل درون‌فرایندی ────────────────────────────

    public function testGet_RunsFullRequestLifecycle(): void
    {
        $this->get('/tvn-test/ping')
            ->assertOk()
            ->assertHeader('Content-Type')
            ->assertJson(['pong' => true, 'n' => 3])
            ->assertJsonStructure(['pong', 'n']);
    }

    public function testPostJson_RoundTripsPayload(): void
    {
        $this->postJson('/tvn-test/echo', ['title' => 'لپ تاپ', 'price' => 1000])
            ->assertCreated()
            ->assertJson(['received' => ['title' => 'لپ تاپ', 'price' => 1000]]);
    }

    public function testPost_FormEncodedRoundTrip(): void
    {
        $this->post('/tvn-test/echo', ['a' => 'b'])
            ->assertCreated()
            ->assertJson(['received' => ['a' => 'b']]);
    }

    public function testGetJson_UnknownRoute_Returns404(): void
    {
        // روت fallback اپلیکیشن (routes/web.php) صفحهٔ HTML ۴۰۴ را رندر می‌کند
        $this->getJson('/tvn-test/definitely-missing')
            ->assertNotFound()
            ->assertSee('۴۰۴');
    }

    public function testGet_HtmlRoute_SeeAndDontSee(): void
    {
        $this->get('/tvn-test/html')
            ->assertOk()
            ->assertSee('سلام دنیا')
            ->assertDontSee('Goodbye');
    }

    public function testGet_RedirectRoute_AssertsLocation(): void
    {
        $this->get('/tvn-test/go')->assertRedirect('/login');
    }

    public function testGet_ResponseCookie_IsVisible(): void
    {
        $this->get('/tvn-test/with-cookie')->assertOk()->assertCookie('tvn_test_cookie');
    }

    public function testWithHeaders_AreSentToKernel(): void
    {
        $this->withHeaders(['X-Test' => 'abc'])
            ->get('/tvn-test/echo-header')
            ->assertOk()
            ->assertJson(['x_test' => 'abc']);
    }

    // ─── خطاها ────────────────────────────────────────────────────────────

    public function testValidationException_RendersFieldErrors(): void
    {
        $this->getJson('/tvn-test/fail422')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJson(['messageId' => 'validation.failed']);
    }

    public function testPost_WithoutCsrfToken_Returns419(): void
    {
        // مسیر معاف‌نشده + نبود توکن → CsrfTokenMismatch
        $this->postJson('/tvn-csrf/protected')->assertStatus(419);
    }

    // ─── actingAs ─────────────────────────────────────────────────────────

    public function testActingAs_SetsGuardUserInProcess(): void
    {
        $this->actingAs(new TestUserFixture(42))
            ->getJson('/tvn-test/me')
            ->assertOk()
            ->assertJson(['id' => 42]);
    }

    public function testGuest_GuardUserIsNull(): void
    {
        $this->getJson('/tvn-test/me')->assertOk()->assertJson(['id' => null]);
    }

    // ─── محیط تست ─────────────────────────────────────────────────────────

    public function testTestEnvironment_UsesInMemorySessionAndLog(): void
    {
        $config = $this->app()->make('config');

        self::assertSame('array', $config->get('session.driver'));
        self::assertSame('array', $config->get('log.default'));
        self::assertSame('testing', $config->get('app.env'));
    }
}

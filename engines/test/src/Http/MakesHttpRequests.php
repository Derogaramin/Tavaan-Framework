<?php

declare(strict_types=1);

namespace Tavaan\Testing\Http;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Contracts\Http\KernelInterface as HttpKernelInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Engine\Auth\AuthManager;
use Tavaan\Engine\Http\Request;
use Tavaan\Testing\AssertionFailed;

/**
 * تَوان | Tavaan — Testing
 * MakesHttpRequests — اجرای درون‌فرایندی درخواست HTTP در تست (DOC-084E §5).
 *
 * ```php
 * final class ProductTest extends TestCase
 * {
 *     use MakesHttpRequests;
 *
 *     public function testIndex_ReturnsProducts(): void
 *     {
 *         $this->get('/api/v1/products')->assertOk()->assertJsonCount(10, 'data');
 *     }
 * }
 * ```
 *
 * اپلیکیشن تست به‌صورت تنبل ساخته می‌شود (`tavaan_test_app()` یا بوت مستقیم) و
 * برای رعایت «تست بدون I/O واقعی» (CON-11) درایور session/log حافظه‌ای می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
trait MakesHttpRequests
{
    protected ?Application $app = null;

    /** @var array<string, string> هدرهای پیش‌فرض همهٔ درخواست‌های بعدی */
    protected array $defaultHeaders = [];

    // ─── متدهای HTTP ──────────────────────────────────────────────────────

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function get(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, $data, $headers);
    }

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, $headers);
    }

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PUT', $uri, $data, $headers);
    }

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PATCH', $uri, $data, $headers);
    }

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('DELETE', $uri, $data, $headers);
    }

    // ─── JSON ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function getJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('GET', $uri, $data, $headers);
    }

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function postJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('POST', $uri, $data, $headers);
    }

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function putJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('PUT', $uri, $data, $headers);
    }

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function patchJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('PATCH', $uri, $data, $headers);
    }

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function deleteJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('DELETE', $uri, $data, $headers);
    }

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function json(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call($method, $uri, $data, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            ...$headers,
        ]);
    }

    /**
     * اجرای کامل یک درخواست از Kernel (درون‌فرایندی — AC-TEST-02).
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    protected function call(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $request = Request::create(
            $uri,
            $method,
            $data,
            [],
            [],
            $this->transformHeaders([...$this->defaultHeaders, ...$headers]),
        );

        $kernel = $this->httpKernel();
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return new TestResponse($response);
    }

    // ─── زمینهٔ درخواست ───────────────────────────────────────────────────

    /**
     * هدرهای پیش‌فرض درخواست‌های بعدی (ادغام شونده).
     *
     * @param array<string, string> $headers
     */
    protected function withHeaders(array $headers): static
    {
        $this->defaultHeaders = [...$this->defaultHeaders, ...$headers];

        return $this;
    }

    /** کاربر معتبر برای گارد داده‌شده (in-memory — بدون session/DB). */
    protected function actingAs(Authenticatable $user, string $guard = 'web'): static
    {
        $this->app()->make(AuthManager::class)->guard($guard)->setUser($user);

        return $this;
    }

    /** بازگرداندن گارد به وضعیت مهمان (پاک‌کردن کاربر — in-memory). */
    protected function asGuest(string $guard = 'web'): static
    {
        $instance = $this->app()->make(AuthManager::class)->guard($guard);
        if (\method_exists($instance, 'forgetUser')) {
            $instance->forgetUser();
        }

        return $this;
    }

    // ─── اپلیکیشن ─────────────────────────────────────────────────────────

    protected function app(): Application
    {
        return $this->app ??= $this->createApplication();
    }

    protected function createApplication(): Application
    {
        $app = \function_exists('tavaan_test_app')
            ? \tavaan_test_app()
            : ApplicationBuilder::create((string) TVN_BASE_PATH)->forTesting()->boot();

        // 🔒 تست بدون I/O واقعی (CON-11): session/log حافظه‌ای
        if ($app->bound('config')) {
            $config = $app->make('config');
            if ($config->get('session.driver', 'array') === 'file') {
                $config->set('session.driver', 'array');
            }
            if (!\in_array($config->get('log.default', 'array'), ['array', 'null'], true)) {
                $config->set('log.default', 'array');
            }
        }

        return $app;
    }

    private function httpKernel(): HttpKernelInterface
    {
        $kernel = $this->app()->make(HttpKernelInterface::class);
        if (!$kernel instanceof HttpKernelInterface) {
            throw AssertionFailed::for('HttpKernel is not bound in the test application.');
        }

        return $kernel;
    }

    /**
     * تبدیل هدرها به کلیدهای `HTTP_*` سرور (مطابق قرارداد Request::create).
     *
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private function transformHeaders(array $headers): array
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $key = \str_replace('-', '_', \strtoupper((string) $name));
            $server[\str_starts_with($key, 'HTTP_') ? $key : 'HTTP_' . $key] = (string) $value;
        }

        return $server;
    }
}

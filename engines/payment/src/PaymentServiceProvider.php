<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\HttpClient\Factory;
use Tavaan\Engine\Payment\Gateways\ConfigWalletStore;
use Tavaan\Engine\Payment\Gateways\WalletStoreInterface;

/**
 * تَوان | Tavaan — Payment
 * PaymentServiceProvider — ثبت PaymentManager (`payment`) + routeهای callback/notify.
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        PaymentResolver::set($this->app);

        $this->app->singleton(PaymentManager::class, static function (ContainerInterface $c): PaymentManager {
            /** @var array<string, mixed> $config */
            $config = $c->bound('config') ? (array) $c->make('config')->get('payment', []) : [];
            $config['app_url'] ??= $c->bound('config') ? (string) $c->make('config')->get('app.url', '') : '';

            $events = null;
            if ($c->bound(DispatcherInterface::class)) {
                $candidate = $c->make(DispatcherInterface::class);
                $events = $candidate instanceof DispatcherInterface ? $candidate : null;
            }

            $http = null;
            if ($c->bound('http.client')) {
                $candidate = $c->make('http.client');
                $http = $candidate instanceof Factory ? $candidate : null;
            }

            return new PaymentManager($config, $events, $http);
        });
        $this->app->alias(PaymentManager::class, 'payment');

        // کیف پول داخلی — پیش‌فرض config-based (اپلیکیشن می‌تواند override کند)
        if (!$this->app->bound('payment.wallet')) {
            $this->app->singleton('payment.wallet', static function (ContainerInterface $c): WalletStoreInterface {
                /** @var array<string, mixed> $accounts */
                $accounts = $c->bound('config')
                    ? (array) $c->make('config')->get('payment.gateways.wallet.accounts', [])
                    : [];

                return new ConfigWalletStore($accounts);
            });
        }
    }

    public function boot(): void
    {
        $this->registerRoutes();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [PaymentManager::class, 'payment', 'payment.wallet'];
    }

    /**
     * routeهای پیش‌فرض (DOC-073 §4):
     * - ANY /payment/callback — بازگشت کاربر (verify امن)
     * - POST /payment/notify/{gateway} — IPN/Webhook با بررسی امضا
     */
    private function registerRoutes(): void
    {
        if (!$this->app->bound('router') || !\class_exists(\Tavaan\Engine\Http\Response::class)) {
            return;
        }

        /** @var \Tavaan\Engine\Http\Routing\Router $router */
        $router = $this->app->make('router');
        $app = $this->app;

        $callback = static function (\Tavaan\Engine\Http\Request $request) use ($app): \Tavaan\Engine\Http\Response {
            /** @var PaymentManager $payments */
            $payments = $app->make('payment');

            try {
                $outcome = $payments->handleCallback($request->all(), $request->input('gateway') ?: null);

                return new \Tavaan\Engine\Http\Response('', 302, ['Location' => $outcome->redirectUrl]);
            } catch (Exceptions\PaymentNotFoundException $e) {
                return new \Tavaan\Engine\Http\Response(
                    (string) \json_encode(['error' => $e->messageId], JSON_UNESCAPED_UNICODE),
                    404,
                    ['Content-Type' => 'application/json'],
                );
            }
        };

        $router->get('/payment/callback', $callback);
        $router->post('/payment/callback', $callback);

        $router->post('/payment/notify/{gateway}', static function (\Tavaan\Engine\Http\Request $request, string $gateway) use ($app): \Tavaan\Engine\Http\Response {
            /** @var PaymentManager $payments */
            $payments = $app->make('payment');

            try {
                $outcome = $payments->notify($gateway, $request->body(), $request->headers());

                return new \Tavaan\Engine\Http\Response(
                    (string) \json_encode([
                        'ok' => $outcome->isSuccess(),
                        'status' => $outcome->status->value,
                    ]),
                    200,
                    ['Content-Type' => 'application/json'],
                );
            } catch (Exceptions\SignatureMismatchException) {
                return new \Tavaan\Engine\Http\Response('{"error":"signature_mismatch"}', 403, ['Content-Type' => 'application/json']);
            } catch (Exceptions\PaymentException $e) {
                return new \Tavaan\Engine\Http\Response(
                    (string) \json_encode(['error' => $e->messageId], JSON_UNESCAPED_UNICODE),
                    422,
                    ['Content-Type' => 'application/json'],
                );
            }
        });
    }
}

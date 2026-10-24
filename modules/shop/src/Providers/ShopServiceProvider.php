<?php

declare(strict_types=1);

namespace Modules\Shop\Providers;

use Modules\Comment\Domain\Services\CommentModerator;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Modules\Content\Domain\Services\ExcerptGenerator;
use Modules\Content\Domain\Services\Markdown;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Shop\Application\UseCases\AdminProductService;
use Modules\Shop\Application\UseCases\CartService;
use Modules\Shop\Application\UseCases\CatalogService;
use Modules\Shop\Application\UseCases\CheckoutService;
use Modules\Shop\Application\UseCases\OrderWorkflowService;
use Modules\Shop\Application\UseCases\PlaceOrderService;
use Modules\Shop\Application\UseCases\ReviewService;
use Modules\Shop\Contracts\PaymentGatewayPort;
use Modules\Shop\Contracts\ProductReaderInterface;
use Modules\Shop\Domain\Repositories\CartRepositoryInterface;
use Modules\Shop\Domain\Repositories\CouponRepositoryInterface;
use Modules\Shop\Domain\Repositories\OrderRepositoryInterface;
use Modules\Shop\Domain\Repositories\ProductRepositoryInterface;
use Modules\Shop\Domain\Repositories\ShopCategoryRepositoryInterface;
use Modules\Shop\Domain\Services\CouponService;
use Modules\Shop\Domain\Services\InventoryService;
use Modules\Shop\Domain\Services\PricingService;
use Modules\Shop\Infrastructure\Notifications\OrderNotifier;
use Modules\Shop\Infrastructure\Persistence\DatabaseCartRepository;
use Modules\Shop\Infrastructure\Persistence\DatabaseCouponRepository;
use Modules\Shop\Infrastructure\Persistence\DatabaseOrderRepository;
use Modules\Shop\Infrastructure\Persistence\DatabaseProductRepository;
use Modules\Shop\Infrastructure\Persistence\DatabaseShopCategoryRepository;
use Modules\Shop\Infrastructure\Persistence\DatabaseShopCommentStore;
use Modules\Shop\Infrastructure\ProductReaderAdapter;
use Modules\Shop\Infrastructure\Gateways\EnginePaymentGateway;
use Modules\Shop\Infrastructure\Gateways\SandboxPaymentGateway;
use Modules\Shop\Infrastructure\Search\ProductSearchIndexer;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * ShopServiceProvider — bindingها + routes/lang + listenerها.
 *
 * 🔒 ARCH-003: فقط Core + Contracts ماژول‌های هم‌خانواده + SDK مجاز.
 * Policyها از راه config اعلامی (modules/shop/config/rbac.php).
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class ShopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = fn (string $key = 'shop'): array => $this->app->bound('config')
            ? (array) $this->app->make('config')->get($key, [])
            : [];

        // ── Infrastructure: مخزن‌ها ──────────────────────────────────────
        $this->app->singleton(ProductRepositoryInterface::class, static fn (): ProductRepositoryInterface => new DatabaseProductRepository());
        $this->app->singleton(ShopCategoryRepositoryInterface::class, static fn (): ShopCategoryRepositoryInterface => new DatabaseShopCategoryRepository());
        $this->app->singleton(CartRepositoryInterface::class, static fn (): CartRepositoryInterface => new DatabaseCartRepository());
        $this->app->singleton(OrderRepositoryInterface::class, static fn (): OrderRepositoryInterface => new DatabaseOrderRepository());
        $this->app->singleton(CouponRepositoryInterface::class, static fn (): CouponRepositoryInterface => new DatabaseCouponRepository());
        $this->app->singleton(DatabaseShopCommentStore::class, static fn (): DatabaseShopCommentStore => new DatabaseShopCommentStore());

        // ── Domain Services ──────────────────────────────────────────────
        $this->app->singleton(PricingService::class, fn (): PricingService => new PricingService($config()));
        $this->app->singleton(InventoryService::class, static fn (): InventoryService => new InventoryService());
        $this->app->singleton(CouponService::class, static fn (ContainerInterface $c): CouponService => new CouponService($c->make(CouponRepositoryInterface::class)));

        // ── درگاه پرداخت (driver از config) ──────────────────────────────
        $this->app->singleton(PaymentGatewayPort::class, function () use ($config): PaymentGatewayPort {
            $payment = (array) ($config()['payment'] ?? []);
            $driver = (string) ($payment['driver'] ?? 'sandbox');

            return $driver === 'engine'
                ? new EnginePaymentGateway((string) ($payment['gateway'] ?? ''))
                : new SandboxPaymentGateway();
        });
        $this->app->alias(PaymentGatewayPort::class, 'shop.payment');

        // ── قرارداد عمومی ─────────────────────────────────────────────────
        $this->app->singleton(ProductReaderInterface::class, static fn (ContainerInterface $c): ProductReaderInterface => new ProductReaderAdapter($c->make(ProductRepositoryInterface::class)));
        $this->app->alias(ProductReaderInterface::class, 'shop.products');

        // ── Application: UseCaseها ───────────────────────────────────────
        $this->app->singleton(AdminProductService::class, fn (ContainerInterface $c): AdminProductService => new AdminProductService(
            products: $c->make(ProductRepositoryInterface::class),
            categories: $c->make(ShopCategoryRepositoryInterface::class),
            inventory: $c->make(InventoryService::class),
            markdown: $c->make(Markdown::class),
            excerpts: $c->make(ExcerptGenerator::class),
            config: $config(),
        ));

        $this->app->singleton(CatalogService::class, fn (ContainerInterface $c): CatalogService => new CatalogService(
            products: $c->make(ProductRepositoryInterface::class),
            categories: $c->make(ShopCategoryRepositoryInterface::class),
            users: $c->make(UserReaderInterface::class),
            config: $config(),
        ));

        $this->app->singleton(CartService::class, static fn (ContainerInterface $c): CartService => new CartService(
            carts: $c->make(CartRepositoryInterface::class),
            products: $c->make(ProductRepositoryInterface::class),
            coupons: $c->make(CouponService::class),
            pricing: $c->make(PricingService::class),
        ));

        $this->app->singleton(PlaceOrderService::class, fn (ContainerInterface $c): PlaceOrderService => new PlaceOrderService(
            carts: $c->make(CartRepositoryInterface::class),
            orders: $c->make(OrderRepositoryInterface::class),
            products: $c->make(ProductRepositoryInterface::class),
            inventory: $c->make(InventoryService::class),
            pricing: $c->make(PricingService::class),
            coupons: $c->make(CouponService::class),
            config: $config(),
        ));

        $this->app->singleton(OrderWorkflowService::class, static fn (ContainerInterface $c): OrderWorkflowService => new OrderWorkflowService(
            orders: $c->make(OrderRepositoryInterface::class),
            inventory: $c->make(InventoryService::class),
            gateway: $c->make(PaymentGatewayPort::class),
        ));

        $this->app->singleton(CheckoutService::class, static fn (ContainerInterface $c): CheckoutService => new CheckoutService(
            orders: $c->make(OrderRepositoryInterface::class),
            workflow: $c->make(OrderWorkflowService::class),
            gateway: $c->make(PaymentGatewayPort::class),
        ));

        $this->app->singleton(ReviewService::class, fn (ContainerInterface $c): ReviewService => new ReviewService(
            comments: $c->make(DatabaseShopCommentStore::class),
            moderator: $c->make(CommentModerator::class),
            config: (array) ($config()['reviews'] ?? []),
        ));

        // ── Infrastructure: شنونده‌ها ────────────────────────────────────
        $this->app->singleton(ProductSearchIndexer::class, fn (ContainerInterface $c): ProductSearchIndexer => new ProductSearchIndexer(
            products: $c->make(ProductRepositoryInterface::class),
            config: (array) ($config()['search'] ?? []),
        ));

        $this->app->singleton(OrderNotifier::class, fn (ContainerInterface $c): OrderNotifier => new OrderNotifier(
            orders: $c->make(OrderRepositoryInterface::class),
            users: $c->make(UserReaderInterface::class),
            container: $c,
            config: (array) ($config()['notifications'] ?? []),
        ));

        // CommentThreadBuilder مصرف‌کنندهٔ ویوها — از ماژول comment
        $this->app->singleton('shop.reviews.thread', static fn (ContainerInterface $c): CommentThreadBuilder => $c->make(CommentThreadBuilder::class));

        // ExcerptGenerator/Markdown برای نوشتن توضیحات محصول (admin)
        $this->app->singleton('shop.markdown', static fn (ContainerInterface $c): Markdown => $c->make(Markdown::class));
        $this->app->singleton('shop.excerpt', static fn (ContainerInterface $c): ExcerptGenerator => $c->make(ExcerptGenerator::class));
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        $this->registerLang($moduleRoot);
        $this->registerRoutes($moduleRoot);
        $this->registerListeners();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            AdminProductService::class,
            ProductRepositoryInterface::class,
            ShopCategoryRepositoryInterface::class,
            CartRepositoryInterface::class,
            OrderRepositoryInterface::class,
            CouponRepositoryInterface::class,
            PaymentGatewayPort::class,
            ProductReaderInterface::class,
            CatalogService::class,
            CartService::class,
            PlaceOrderService::class,
            CheckoutService::class,
            OrderWorkflowService::class,
            ReviewService::class,
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function registerLang(string $moduleRoot): void
    {
        if (!$this->app->bound('lang')) {
            return;
        }
        $translator = $this->app->make('lang');
        if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
            $translator->addNamespace('shop', $moduleRoot . '/lang');
        }
    }

    private function registerRoutes(string $moduleRoot): void
    {
        foreach (['web', 'api', 'admin'] as $channel) {
            $file = $moduleRoot . '/routes/' . $channel . '.php';
            if (\is_file($file)) {
                require $file;
            }
        }
    }

    /** سیم‌کشی رویدادها (DOC-093 §6). */
    private function registerListeners(): void
    {
        if (!$this->app->bound('events')) {
            return;
        }

        $app = $this->app;

        Event::listen('shop.product.updated', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ProductSearchIndexer::class)->onProductUpdated($event, $payload);
            // invalidate کش کاتالوگ (DOC-093 §6: cache)
            try {
                Cache::tags(['shop'])->flush();
            } catch (\Throwable) {
                // درایور بدون tag
            }
        });
        Event::listen('shop.product.deleted', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ProductSearchIndexer::class)->onProductDeleted($event, $payload);
        });
        Event::listen('shop.order.paid', static function (string $event, mixed $payload) use ($app): void {
            $app->make(OrderNotifier::class)->onPaid($event, $payload);
        });
        Event::listen('shop.order.shipped', static function (string $event, mixed $payload) use ($app): void {
            $app->make(OrderNotifier::class)->onShipped($event, $payload);
        });
        Event::listen('shop.order.canceled', static function (string $event, mixed $payload) use ($app): void {
            $app->make(OrderNotifier::class)->onCanceled($event, $payload);
        });
    }
}

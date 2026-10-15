<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Bootstrap\LoadConfiguration;
use Tavaan\Core\Bootstrap\LoadEnvironmentVariables;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Exceptions\Core\ConfigException;
use Tavaan\Core\Facade\Facades\Config as ConfigFacade;
use Tavaan\Engine\Config\EnvRepository;
use Tavaan\Engine\Config\Repository;
use Tavaan\Engine\Event\EventDispatcher;
use Tavaan\Engine\Log\LogManager;
use Tavaan\Engine\Module\ModuleRegistry;
use Tavaan\Testing\TestCase;

/**
 * تست بوت کامل — AC-BOOT و TVN-0001..0011 (سطح Kernel).
 */
final class BootstrapTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = \tavaan_test_app();
    }

    /** AC-BOOT-01: پروژه بدون vendor/ بالا می‌آید */
    public function testBootsWithoutVendorDirectory(): void
    {
        $this->assertFalse(\is_dir(TVN_BASE_PATH . '/vendor'), 'zero-dependency: no vendor dir allowed');
        $this->assertInstanceOf(Application::class, $this->app);
    }

    /** Kernel: سرویس‌های اصلی bound و درست هستند */
    public function testCoreServicesAreBound(): void
    {
        $this->assertInstanceOf(Repository::class, $this->app->make('config'));
        $this->assertInstanceOf(EventDispatcher::class, $this->app->make('events'));
        $this->assertInstanceOf(DispatcherInterface::class, $this->app->make(DispatcherInterface::class));
        $this->assertInstanceOf(LogManager::class, $this->app->make('log'));
        $this->assertInstanceOf(ModuleRegistry::class, $this->app->make('modules'));
        $this->assertSame('Tavaan', $this->app->make('config')->get('app.name'));
    }

    /** AC-BOOT-09: Bootstrapperها idempotent */
    public function testBootstrappersAreIdempotent(): void
    {
        $loadConfig = new LoadConfiguration();
        $loadConfig->bootstrap($this->app);   // بار دوم
        $loadConfig->bootstrap($this->app);   // بار سوم
        $this->assertSame('Tavaan', $this->app->make('config')->get('app.name'));

        $loadEnv = new LoadEnvironmentVariables();
        $loadEnv->bootstrap($this->app);
        $this->assertTrue(true);
    }

    /** AC-BOOT-07: production + debug=true → خطای بوت */
    public function testProductionDebugCombinationFails(): void
    {
        $_SERVER['TVN_APP_ENV'] = 'production';
        $_SERVER['TVN_APP_DEBUG'] = 'true';
        try {
            (new LoadEnvironmentVariables())->bootstrap($this->app);
            self::fail('expected boot failure for production+debug');
        } catch (ConfigException $e) {
            $this->assertSame('core.env.debug_in_production', $e->messageId);
        } finally {
            $_SERVER['TVN_APP_ENV'] = 'testing';
            unset($_SERVER['TVN_APP_DEBUG']);
        }
    }

    /** Facade پس از بوت کار می‌کند */
    public function testFacadeResolvesConfig(): void
    {
        $this->assertSame('fa', ConfigFacade::get('app.locale'));
    }

    /** Log از طریق container می‌نویسد (کانال array) */
    public function testLogWritesToArrayChannel(): void
    {
        $this->app->make('config')->set('log.default', 'array');
        \Tavaan\Engine\Log\Logger::resetRequestCounter();

        /** @var LogManager $log */
        $log = $this->app->make('log');
        $log->info('feature.test_message', ['key' => 'value']);

        $channel = $log->channel('array');
        $reflection = new \ReflectionProperty(\Tavaan\Engine\Log\Logger::class, 'handlers');
        $handlers = $reflection->getValue($channel);
        $lines = $handlers[0]->lines();
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('feature.test_message', $lines[0]);
    }

    /** Event + Log + Config با هم (چرخهٔ M1) */
    public function testEventDispatchThroughContainer(): void
    {
        $hits = [];
        $this->app->make('events')->listen('boot.test', static function () use (&$hits): void { $hits[] = 1; });
        $this->app->make('events')->dispatch('boot.test');
        $this->assertCount(1, $hits);
    }

    /** Module registry: همهٔ موتورها کشف و توپولوژیک مرتب می‌شوند */
    public function testModulesDiscoveredAndSorted(): void
    {
        /** @var ModuleRegistry $registry */
        $registry = $this->app->make('modules');
        $sorted = $registry->sorted();
        $ids = \array_map(static fn ($m): string => $m->id(), $sorted);

        $this->assertContains('tavaan.engine.container', $ids);
        $this->assertContains('tavaan.engine.console', $ids);
        $this->assertTrue(\array_search('tavaan.engine.container', $ids) < \array_search('tavaan.engine.config', $ids));
    }

    /** Environment: runtime mode و env */
    public function testRuntimeModeAndEnvironment(): void
    {
        $this->assertTrue($this->app->runningInConsole());
        $this->assertSame('testing', $this->app->environment());
        $this->assertSame(\Tavaan\Core\Application\RuntimeMode::Testing, $this->app->mode());
        $this->assertFalse($this->app->isProduction());
    }

    /** EnvRepository بدون putenv — AC-BOOT-06 سطح رفتاری */
    public function testEnvReadsFromEnvFile(): void
    {
        $env = EnvRepository::instance();
        $this->assertSame('Tavaan', $env->string('TVN_APP_NAME', ''));
        $this->assertSame('Asia/Tehran', $env->string('TVN_APP_TIMEZONE', ''));
        // putenv هرگز صدا زده نشده: getenv نباید مقدار .env را ببیند
        $this->assertFalse(\getenv('TVN_APP_NAME'));
    }

    /** زمان بوت (بدون cache) — هدف ≤ ۸ms warm؛ در CI نرم‌افزار sandbox ممکن است بیشتر باشد */
    public function testBootTimeReasonable(): void
    {
        $start = \microtime(true);
        \tavaan_test_app();
        $ms = (\microtime(true) - $start) * 1000;
        $this->assertLessThan(250.0, $ms, \sprintf('boot took %.1f ms', $ms));
    }
}

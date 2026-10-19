<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Console\Output\Output;
use Tavaan\Engine\Scaffold\Console\Commands\MakeControllerCommand;
use Tavaan\Engine\Scaffold\Console\Commands\MakeEngineCommand;
use Tavaan\Engine\Scaffold\Console\Commands\MakeMiddlewareCommand;
use Tavaan\Engine\Scaffold\Console\Commands\MakeMigrationCommand;
use Tavaan\Engine\Scaffold\Console\Commands\MakeModelCommand;
use Tavaan\Engine\Scaffold\Console\Commands\MakeModuleCommand;
use Tavaan\Engine\Scaffold\Console\Commands\MakeRequestCommand;
use Tavaan\Engine\Scaffold\Console\Commands\MakeTestCommand;
use Tavaan\Engine\Scaffold\GeneratorManager;
use Tavaan\Engine\Scaffold\ScaffoldServiceProvider;
use Tavaan\Support\Str;
use Tavaan\Testing\TestCase;

/**
 * تست تولید کد — DOC-077 §9 (T-02..T-06) از مسیر واقعیِ Command.
 *
 * 🔒 همهٔ فایل‌ها در sys_get_temp_dir تولید می‌شوند (بدون آلودگی مخزن)؛
 * GeneratorManager با basePath موقت در container تست bind می‌شود.
 */
final class ScaffoldTest extends TestCase
{
    private string $tmp = '';

    private Application $app;

    protected function setUp(): void
    {
        $this->tmp = \sys_get_temp_dir() . '/tvn-scaffold-test-' . \getmypid();
        $this->clean($this->tmp);
        \mkdir($this->tmp, 0775, true);

        $this->app = \tavaan_test_app();

        $stubs = (string) TVN_BASE_PATH . '/resources/stubs';
        $tmp = $this->tmp;
        $this->app->singleton(
            GeneratorManager::class,
            static fn (): GeneratorManager => ScaffoldServiceProvider::makeManager($stubs, $tmp),
        );
    }

    protected function tearDown(): void
    {
        $this->clean($this->tmp);
    }

    // ─── T-02: make:model با همهٔ flagها ─────────────────────────────────

    public function testMakeModel_WithAllFlags_GeneratesValidPhp(): void
    {
        $output = $this->run(new MakeModelCommand(), [
            'Product', '--migration', '--factory', '--seeder', '--controller', '--fields=name,price:integer,active:boolean',
        ]);

        self::assertSame(0, $output['code']);
        self::assertStringContainsString('5 فایل ساخته شد', $output['text']);

        $model = $this->tmp . '/app/src/Models/Product.php';
        self::assertFileExistsReadable($model);
        $modelSrc = (string) \file_get_contents($model);
        self::assertStringContainsString('namespace App\\Models;', $modelSrc);
        self::assertStringContainsString("protected string \$table = 'products';", $modelSrc);
        self::assertStringContainsString("['name', 'price', 'active']", $modelSrc);

        // migration با ستون‌های --fields
        $migrations = \glob($this->tmp . '/database/migrations/*_create_products_table.php') ?: [];
        self::assertCount(1, $migrations);
        $migrationSrc = (string) \file_get_contents($migrations[0]);
        self::assertStringContainsString("\$t->integer('price');", $migrationSrc);
        self::assertStringContainsString("\$t->boolean('active');", $migrationSrc);
        self::assertStringContainsString('Schema::create(\'products\'', $migrationSrc);

        // factory/seeder/controller
        $factorySrc = (string) \file_get_contents($this->tmp . '/app/src/Database/Factories/ProductFactory.php');
        self::assertStringContainsString('use App\\Models\\Product;', $factorySrc);
        self::assertStringContainsString('protected string $model = App\\Models\\Product::class;', $factorySrc);
        self::assertFileExistsReadable($this->tmp . '/app/src/Database/Seeders/ProductSeeder.php');
        $controllerSrc = (string) \file_get_contents($this->tmp . '/app/src/Http/Controllers/ProductController.php');
        self::assertStringContainsString('public function index(): Response', $controllerSrc);

        // AC-SCF-01: همهٔ فایل‌های php تولیدی باید php -l پاس شوند
        $this->assertAllGeneratedPhpLints();
    }

    public function testMakeModel_PluralizesTableIrregularly(): void
    {
        $this->run(new MakeModelCommand(), ['Person']);
        $src = (string) \file_get_contents($this->tmp . '/app/src/Models/Person.php');

        self::assertStringContainsString("'people'", $src);
        self::assertSame('people', Str::snake(Str::plural('Person')));
    }

    // ─── T-03: make:module ساختار کامل ───────────────────────────────────

    public function testMakeModule_Hexagonal_FullStructure(): void
    {
        $output = $this->run(new MakeModuleCommand(), ['shop', '--domain=hexagonal']);
        self::assertSame(0, $output['code']);

        $root = $this->tmp . '/modules/shop';
        foreach ([
            '/module.json', '/composer.json', '/README.md',
            '/src/Providers/ShopServiceProvider.php',
            '/config/shop.php',
            '/routes/web.php', '/routes/api.php', '/routes/admin.php',
            '/lang/fa/shop.php', '/lang/en/shop.php',
            '/views/.gitkeep', '/migrations/.gitkeep', '/seeds/.gitkeep', '/tests/Unit/.gitkeep',
            '/src/Http/Controllers/.gitkeep',
            '/src/Domain/Entities/.gitkeep', '/src/Domain/ValueObjects/.gitkeep',
            '/src/Domain/Services/.gitkeep', '/src/Domain/Events/.gitkeep',
            '/src/Application/UseCases/.gitkeep', '/src/Application/Commands/.gitkeep', '/src/Application/Queries/.gitkeep',
            '/src/Infrastructure/Persistence/.gitkeep',
            '/src/Contracts/.gitkeep',
        ] as $relative) {
            self::assertFileExistsReadable($root . $relative);
        }

        // manifest معتبر (AC-SCF-02 — فیلدهای الزامی ManifestParser)
        $manifest = \json_decode((string) \file_get_contents($root . '/module.json'), true);
        self::assertNotNull($manifest);
        foreach (['id', 'name', 'version', 'autoload', 'providers'] as $field) {
            self::assertArrayHasKey($field, (array) $manifest);
        }
        self::assertSame('module.shop', $manifest['id']);
        self::assertSame(['Modules\\Shop\\Providers\\ShopServiceProvider'], $manifest['providers']);

        $providerSrc = (string) \file_get_contents($root . '/src/Providers/ShopServiceProvider.php');
        self::assertStringContainsString('namespace Modules\\Shop\\Providers;', $providerSrc);

        $this->assertAllGeneratedPhpLints();
    }

    public function testMakeModule_LayeredVariant(): void
    {
        $this->run(new MakeModuleCommand(), ['blog', '--domain=layered']);

        self::assertFileExistsReadable($this->tmp . '/modules/blog/src/Models/.gitkeep');
        self::assertFileExistsReadable($this->tmp . '/modules/blog/src/Services/.gitkeep');
        self::assertTrue(!\is_dir($this->tmp . '/modules/blog/src/Domain'));
    }

    // ─── T-04: overwrite با --force + backup ─────────────────────────────

    public function testExistingFile_WarnsWithoutForce_BacksUpWithForce(): void
    {
        $this->run(new MakeModelCommand(), ['Product']);
        $model = $this->tmp . '/app/src/Models/Product.php';
        $original = (string) \file_get_contents($model);
        \file_put_contents($model, "<?php\n// LOCAL-EDIT\n");

        // بدون --force → هشدار، بدون بازنویسی
        $output = $this->run(new MakeModelCommand(), ['Product']);
        self::assertSame(0, $output['code']);
        self::assertStringContainsString('وجود دارد', $output['text']);
        self::assertStringContainsString('LOCAL-EDIT', (string) \file_get_contents($model));
        self::assertTrue(!\is_file($model . '.bak'));

        // با --force → پشتیبان .bak + بازنویسی
        $output = $this->run(new MakeModelCommand(), ['Product', '--force']);
        self::assertSame(0, $output['code']);
        self::assertStringContainsString('LOCAL-EDIT', (string) \file_get_contents($model . '.bak'));
        self::assertSame($original, (string) \file_get_contents($model));
    }

    // ─── T-05: namespace صحیح برای module/app ────────────────────────────

    public function testModuleTarget_UsesModuleNamespaceAndPath(): void
    {
        $this->run(new MakeModuleCommand(), ['shop']);
        $output = $this->run(new MakeRequestCommand(), ['StoreProductRequest', '--module=shop']);
        self::assertSame(0, $output['code']);

        $file = $this->tmp . '/modules/shop/src/Http/Requests/StoreProductRequest.php';
        self::assertFileExistsReadable($file);
        self::assertStringContainsString('namespace Modules\\Shop\\Http\\Requests;', (string) \file_get_contents($file));
    }

    public function testControllerNamespacedName_MapsToSubdirectory(): void
    {
        $output = $this->run(new MakeControllerCommand(), ['Shop/CartController', '--resource', '--api']);
        self::assertSame(0, $output['code']);

        $file = $this->tmp . '/app/src/Http/Controllers/Shop/CartController.php';
        self::assertFileExistsReadable($file);
        $src = (string) \file_get_contents($file);
        self::assertStringContainsString('namespace App\\Http\\Controllers\\Shop;', $src);
        self::assertStringContainsString('Response::json', $src);   // --api
        self::assertStringNotContainsString('function create(', $src);   // API بدون create/edit
    }

    public function testControllerResourceWeb_HasViewActions(): void
    {
        $this->run(new MakeControllerCommand(), ['InvoiceController', '--resource']);
        $src = (string) \file_get_contents($this->tmp . '/app/src/Http/Controllers/InvoiceController.php');

        self::assertStringContainsString('function create(', $src);
        self::assertStringContainsString('function edit(', $src);
        self::assertStringNotContainsString('Response::json', $src);
    }

    public function testControllerInvokable_WithoutResourceFlag(): void
    {
        $this->run(new MakeControllerCommand(), ['PingController']);
        $src = (string) \file_get_contents($this->tmp . '/app/src/Http/Controllers/PingController.php');

        self::assertStringContainsString('public function __invoke(Request $request): Response', $src);
    }

    // ─── T-06: نام نامعتبر → رد ──────────────────────────────────────────

    public function testInvalidNames_AreRejected(): void
    {
        $cases = [
            [MakeModelCommand::class, ['lowercase']],
            [MakeModelCommand::class, ['With-Dash']],
            [MakeModelCommand::class, ['../Escape']],
            [MakeModuleCommand::class, ['BadModule']],
            [MakeModuleCommand::class, ['../evil-module']],
            [MakeMigrationCommand::class, ['NotSnake']],
            [MakeEngineCommand::class, ['UPPER']],
        ];

        foreach ($cases as [$commandClass, $tokens]) {
            $output = $this->run(new $commandClass(), $tokens);
            self::assertSame(1, $output['code'], 'باید رد شود: ' . \implode(' ', $tokens));
            self::assertStringContainsString('✗', $output['text']);
        }

        // هیچ فایلی تولید نشده
        self::assertTrue(!\is_dir($this->tmp . '/app'));
        self::assertTrue(!\is_dir($this->tmp . '/modules'));
    }

    public function testModuleNameWithTraversal_IsSandboxed(): void
    {
        $output = $this->run(new MakeRequestCommand(), ['XRequest', '--module=../../escape']);

        self::assertSame(1, $output['code']);
        self::assertTrue(!\is_dir(\dirname($this->tmp) . '/escape'));
    }

    // ─── سایر Generatorها (پوشش AC-SCF-01) ───────────────────────────────

    public function testOtherGenerators_ProduceValidFiles(): void
    {
        $this->run(new MakeTestCommand(), ['ProductTest', '--unit']);
        $this->run(new MakeMiddlewareCommand(), ['EnsureShopEnabled']);
        $this->run(new MakeMigrationCommand(), ['create_carts_table', '--fields=total:bigInteger']);
        $this->run(new MakeEngineCommand(), ['demo-reporting']);

        self::assertFileExistsReadable($this->tmp . '/tests/Unit/ProductTest.php');
        $testSrc = (string) \file_get_contents($this->tmp . '/tests/Unit/ProductTest.php');
        self::assertStringContainsString('namespace App\\Tests\\Unit;', $testSrc);
        self::assertStringContainsString('extends TestCase', $testSrc);

        self::assertFileExistsReadable($this->tmp . '/app/src/Http/Middleware/EnsureShopEnabledMiddleware.php');

        $migrations = \glob($this->tmp . '/database/migrations/*_create_carts_table.php') ?: [];
        self::assertCount(1, $migrations);
        self::assertStringContainsString("\$t->bigInteger('total');", (string) \file_get_contents($migrations[0]));

        $manifest = \json_decode((string) \file_get_contents($this->tmp . '/engines/demo-reporting/module.json'), true);
        self::assertSame('tavaan.engine.demo-reporting', $manifest['id'] ?? '');

        $this->assertAllGeneratedPhpLints();
    }

    public function testServiceProvider_RegistersAllSeventeenGenerators(): void
    {
        self::assertCount(17, ScaffoldServiceProvider::GENERATORS);

        /** @var GeneratorManager $manager */
        $manager = $this->app->make(GeneratorManager::class);
        foreach (\array_keys(ScaffoldServiceProvider::GENERATORS) as $key) {
            self::assertTrue($manager->has($key), 'کلید ثبت‌نشده: ' . $key);
        }
    }

    // ─── ابزار ────────────────────────────────────────────────────────────

    /**
     * اجرای یک Command روی app تست با خروجی در حافظه.
     *
     * @param list<string> $tokens
     *
     * @return array{code: int, text: string}
     */
    private function run(object $command, array $tokens): array
    {
        $stream = \fopen('php://memory', 'w+');
        self::assertTrue(\is_resource($stream));

        /** @var \Tavaan\Engine\Console\Command $command */
        $code = $command->run($this->app, $tokens, new Output($stream, false));

        \rewind($stream);
        $text = (string) \stream_get_contents($stream);
        \fclose($stream);

        return ['code' => $code, 'text' => $text];
    }

    /** AC-SCF-01: هر فایل PHP تولیدشده باید `php -l` پاس شود. */
    private function assertAllGeneratedPhpLints(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS),
        );
        $checked = 0;
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || !\str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            $result = [];
            $status = 0;
            \exec(\escapeshellarg(PHP_BINARY) . ' -l ' . \escapeshellarg($file->getPathname()) . ' 2>&1', $result, $status);
            $checked++;
            self::assertSame(0, $status, 'php -l شکست: ' . $file->getPathname() . ' → ' . \implode(' ', $result));
        }

        self::assertGreaterThan(0, $checked);
    }

    private function assertFileExistsReadable(string $path): void
    {
        self::assertTrue(\is_file($path), 'فایل وجود ندارد: ' . $path);
        self::assertTrue(\is_readable($path), 'فایل خواندنی نیست: ' . $path);
    }

    private function clean(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var \SplFileInfo $item */
        foreach ($items as $item) {
            $item->isDir() ? \rmdir($item->getPathname()) : \unlink($item->getPathname());
        }
        \rmdir($dir);
    }
}

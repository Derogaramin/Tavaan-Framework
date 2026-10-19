<?php

/**
 * تَوان | Tavaan — نمونهٔ چارچوب تست خودنوشته (M8 / DOC-084E)
 *
 * اجرا: php examples/testing/test-framework.php
 *
 * نمایش: Faker deterministic → Mock صفر‌وابستگی (بدون eval) →
 * TestResponse assertions → Native Runner روی یک Suite موقت.
 */

declare(strict_types=1);

use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Engine\Console\Output\Output;
use Tavaan\Engine\Http\Response;
use Tavaan\Testing\Database\Faker;
use Tavaan\Testing\Http\TestResponse;
use Tavaan\Testing\Mock\Mock;
use Tavaan\Testing\TestRunner;

require \dirname(__DIR__, 2) . '/core/bootstrap.php';

$app = ApplicationBuilder::create(TVN_BASE_PATH)->forConsole()->boot();

echo '── ۱) Faker deterministic (seed=42) ─────────────', PHP_EOL;
$faker = new Faker(42);
echo '  نام:        ', $faker->name(), PHP_EOL;
echo '  ایمیل:      ', $faker->email(), PHP_EOL;
echo '  موبایل:     ', $faker->mobile(), PHP_EOL;
echo '  کد ملی:     ', $faker->nationalCode(), ' (checksum معتبر)', PHP_EOL;
echo '  جلالی:      ', $faker->jalaliDate(), PHP_EOL;
echo '  تکرارپذیر:  ', (new Faker(42))->name() === (new Faker(42))->name() ? 'بله — seed=42 دو بار → خروجی یکسان' : 'خیر', PHP_EOL;
echo '  unique:     ', (new Faker(42))->unique()->email(), PHP_EOL;

echo PHP_EOL, '── ۲) Mock بدون eval/Mockery ─────────────────────', PHP_EOL;

interface ProductRepositoryDemo
{
    public function find(int $id): ?string;

    public function save(string $product): bool;
}

$repo = Mock::of(ProductRepositoryDemo::class)
    ->shouldReceive('find')->with(7)->andReturn('لپ‌تاپ')
    ->shouldReceive('save')->once()->andReturn(true)
    ->getMock();

echo '  find(7):    ', var_export($repo->find(7), true), PHP_EOL;
echo '  save():     ', var_export($repo->save('کیبورد'), true), PHP_EOL;
echo '  فراخوانی‌ها: ', \count($repo->tvnMockHandler()->calls()), PHP_EOL;

echo PHP_EOL, '── ۳) TestResponse assertions ────────────────────', PHP_EOL;
$response = new TestResponse(Response::json(['data' => [['id' => 1, 'title' => 'الف']]], 201));
$response->assertCreated()
    ->assertJson(['data' => [['id' => 1]]])
    ->assertJsonStructure(['data' => [['id', 'title']]])
    ->assertJsonCount(1, 'data');
echo '  ✓ زنجیرهٔ assertCreated→assertJson→assertJsonStructure→assertJsonCount پاس شد', PHP_EOL;

echo PHP_EOL, '── ۴) Native Runner (بدون PHPUnit) ───────────────', PHP_EOL;

// Suite موقت در حافظهٔ دیسک (tmp) — مثل پروژهٔ واقعی کشف و اجرا می‌شود
$dir = \sys_get_temp_dir() . '/tvn-demo-suite';
if (!\is_dir($dir)) {
    \mkdir($dir, 0775, true);
}
\file_put_contents($dir . '/DemoMathTest.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace TvnDemo;

use Tavaan\Testing\Group;
use Tavaan\Testing\TestCase;

#[Group('demo')]
final class DemoMathTest extends TestCase
{
    public function testSum_Works(): void
    {
        self::assertSame(4, 2 + 2);
    }

    public function testString_ContainsPersian(): void
    {
        self::assertStringContainsString('توان', 'فریمورک توان');
    }
}
PHP);

$runner = new TestRunner(new Output());
$exitCode = $runner->run([$dir]);

unset($app);
exit($exitCode);

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report\Tests\Feature;

require_once __DIR__ . '/../Fixtures/ReportFixtures.php';

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Report\Export\XlsxWriter;
use Tavaan\Engine\Report\Exceptions\ReportException;
use Tavaan\Engine\Report\ReportBuilder;
use Tavaan\Engine\Report\ReportManager;
use Tavaan\Engine\Report\Tests\Fixtures\ROrderStatus;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Report — DOC-072 §10 (T-01..T-09) + §11 (AC-RPT-10).
 */
final class ReportTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        Clock::setTestNow(null);

        $this->app = \tavaan_test_app();
        $config = $this->app->make('config');
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
    }

    private function manager(): ReportManager
    {
        return $this->app->make('report');
    }

    /** @return list<array<string, mixed>> */
    private function salesRows(): array
    {
        return [
            ['created_at' => '2026-09-01 10:00:00', 'total' => 1_500_000, 'status' => 'paid', 'city' => 'تهران'],
            ['created_at' => '2026-09-15 12:30:00', 'total' => 2_300_000, 'status' => 'paid', 'city' => 'مشهد'],
            ['created_at' => '2026-10-02 09:15:00', 'total' => 900_000, 'status' => 'pending', 'city' => 'تهران'],
            ['created_at' => '2026-10-11 18:45:00', 'total' => 4_100_000, 'status' => 'canceled', 'city' => 'شیراز'],
            ['created_at' => '2026-10-17 08:00:00', 'total' => 750_000, 'status' => 'paid', 'city' => '<script>alert(1)</script>'],
        ];
    }

    private function salesReport(): ReportBuilder
    {
        return $this->manager()->build('sales')
            ->dataset(fn (): array => $this->salesRows())
            ->addField('created_at', label: 'تاریخ', type: 'date', format: 'jalali')
            ->addField('total', label: 'مبلغ', type: 'money', aggregate: 'sum')
            ->addField('status', label: 'وضعیت')
            ->addField('city', label: 'شهر')
            ->addFilter('date_range', field: 'created_at', type: 'daterange')
            ->addFilter('status', field: 'status', type: 'select', options: ROrderStatus::cases())
            ->addFilter('city', field: 'city', type: 'text')
            ->addFilter('min_total', field: 'total', type: 'number');
    }

    private function seedDatabase(): void
    {
        $schema = $this->app->make('schema');
        $schema->create('tvn_test_sales', function (Blueprint $t): void {
            $t->id();
            $t->dateTime('created_at', 3);
            $t->bigInteger('total');
            $t->string('status', 20);
            $t->string('city', 100);
        });

        $db = $this->app->make('db');
        foreach ($this->salesRows() as $row) {
            $db->table('tvn_test_sales')->insert($row);
        }
    }

    // ─── T-01: Dataset + fields + filters ─────────────────────────────────

    public function testArrayDatasetWithFieldsAndProjection(): void
    {
        $result = $this->salesReport()->run();

        self::assertSame(5, $result->count());
        self::assertSame(['تاریخ', 'مبلغ', 'وضعیت', 'شهر'], $result->headers());
        self::assertSame(9_550_000, $result->totals()['total']);

        // projection — فقط فیلدهای اعلام‌شده
        self::assertSame(['created_at', 'total', 'status', 'city'], \array_keys($result->rows()[0]));
    }

    public function testFiltersApplied(): void
    {
        // daterange
        $result = $this->salesReport()->run(['date_range' => ['2026-10-01', '2026-10-31']]);
        self::assertSame(3, $result->count());

        // select (تک‌مقداری)
        $result = $this->salesReport()->run(['status' => 'paid']);
        self::assertSame(3, $result->count());

        // select خارج از options → 🔒 نادیده (allowlist)
        $result = $this->salesReport()->run(['status' => 'hacked']);
        self::assertSame(5, $result->count());

        // text (LIKE)
        $result = $this->salesReport()->run(['city' => 'تهران']);
        self::assertSame(2, $result->count());

        // ترکیبی
        $result = $this->salesReport()->run([
            'date_range' => ['from' => '2026-10-01', 'to' => '2026-10-31'],
            'status' => ['paid', 'canceled'],
        ]);
        self::assertSame(2, $result->count());

        // کلید ناشناخته → نادیده
        $result = $this->salesReport()->run(['evil_column' => 'x']);
        self::assertSame(5, $result->count());
    }

    public function testQueryBuilderDatasetFiltersInSql(): void
    {
        $this->seedDatabase();
        $db = $this->app->make('db');

        $report = $this->manager()->build('sales-db')
            ->dataset(fn (): mixed => $db->table('tvn_test_sales')->select('created_at', 'total', 'status', 'city'))
            ->addField('created_at', label: 'تاریخ', type: 'date', format: 'jalali')
            ->addField('total', label: 'مبلغ', type: 'money', aggregate: 'sum')
            ->addField('status', label: 'وضعیت')
            ->addField('city', label: 'شهر')
            ->addFilter('date_range', field: 'created_at', type: 'daterange')
            ->addFilter('status', field: 'status', type: 'select', options: ROrderStatus::cases())
            ->orderBy('total', 'desc');

        $result = $report->run(['date_range' => ['2026-10-01', '2026-10-31'], 'status' => 'paid']);
        self::assertSame(1, $result->count());   // فقط 1405-07-25 (paid)
        self::assertSame(750_000, $result->totals()['total']);

        // orderBy desc روی QB
        $all = $report->run();
        self::assertSame(4_100_000, (int) $all->rows()[0]['total']);

        // تاریخ جلالی
        $formatted = $all->formattedRows();
        self::assertSame('1405/07/25', $formatted[4]['created_at']);
    }

    public function testDeriveFieldAndOrderBy(): void
    {
        $report = $this->salesReport()
            ->addField('month', label: 'ماه', derive: static fn (array $row): string => \substr((string) $row['created_at'], 0, 7))
            ->orderBy('total', 'desc');

        $result = $report->run();
        self::assertSame(4_100_000, (int) $result->rows()[0]['total']);
        self::assertSame('2026-10', $result->rows()[4]['month']);   // ۷۵۰٬۰۰۰ (۲۰۲۶-۱۰-۱۷)
    }

    // ─── T-02: groupBy + subtotal ─────────────────────────────────────────

    public function testGroupByWithSubtotals(): void
    {
        $result = $this->salesReport()
            ->groupBy('status', subtotal: ['total' => 'sum'])
            ->run();

        $subtotals = $result->subtotals();
        self::assertSame(['paid', 'pending', 'canceled'], \array_keys($subtotals));
        self::assertSame(4_550_000, $subtotals['paid']['total']);
        self::assertSame(900_000, $subtotals['pending']['total']);
        self::assertSame(4_100_000, $subtotals['canceled']['total']);
        self::assertSame('status', $result->groupField());
    }

    // ─── T-03: pivot ──────────────────────────────────────────────────────

    public function testPivotTable(): void
    {
        $result = $this->salesReport()
            ->pivot(
                rows: 'status',
                columns: static fn (array $row): string => \substr((string) $row['created_at'], 0, 7),
                values: 'total',
                aggregate: 'sum',
            )
            ->run();

        $pivot = $result->pivot();
        self::assertNotNull($pivot);
        self::assertSame(['2026-09', '2026-10'], $pivot['columns']);
        self::assertSame(3_800_000, $pivot['data']['paid']['2026-09']);
        self::assertSame(750_000, $pivot['data']['paid']['2026-10']);
        self::assertSame(900_000, $pivot['data']['pending']['2026-10']);
        self::assertNull($pivot['data']['pending']['2026-09']);
        self::assertSame(4_100_000, $pivot['data']['canceled']['2026-10']);
    }

    // ─── T-04: CSV export ─────────────────────────────────────────────────

    public function testCsvExportWithBomAndDelimiter(): void
    {
        $csv = $this->manager()->export($this->salesReport(), 'csv');

        // 🔒 BOM برای UTF-8 در Excel
        self::assertTrue(\str_starts_with($csv, "\xEF\xBB\xBF"));
        // 🔒 delimiter `;`
        self::assertStringContainsString('تاریخ;مبلغ;وضعیت;شهر', $csv);
        // تاریخ جلالی + مبلغ raw (عددی برای Excel)
        self::assertStringContainsString('1405/06/10;1500000;paid;تهران', $csv);

        $lines = \explode("\n", \trim(\substr($csv, 3)));
        self::assertCount(6, $lines);   // header + 5 ردیف
    }

    // ─── T-05: xlsx export ────────────────────────────────────────────────

    public function testXlsxExportOpensAsValidZip(): void
    {
        $content = $this->manager()->export($this->salesReport(), 'xlsx');

        // ZIP magic
        self::assertTrue(\str_starts_with($content, "PK\x03\x04"));

        $path = \sys_get_temp_dir() . '/tvn-report-test-' . \getmypid() . '.xlsx';
        \file_put_contents($path, $content);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path) === true, 'xlsx باید با ZipArchive باز شود (AC-RPT-10)');

        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $strings = (string) $zip->getFromName('xl/sharedStrings.xml');
        $workbook = (string) $zip->getFromName('xl/workbook.xml');

        self::assertStringContainsString('<sheetData>', $sheet);
        // سربرگ bold (style s="1") و shared string
        self::assertStringContainsString('<c r="A1" t="s" s="1"><v>0</v></c>', $sheet);
        self::assertStringContainsString('تاریخ', $strings);
        self::assertStringContainsString('name="sales"', $workbook);
        // عدد خام → سلول عددی (بدون t="s")
        self::assertStringContainsString('<v>1500000</v>', $sheet);

        $zip->close();
        @\unlink($path);
    }

    public function testXlsxColumnNamingBeyondZ(): void
    {
        self::assertSame('A', XlsxWriter::colName(0));
        self::assertSame('Z', XlsxWriter::colName(25));
        self::assertSame('AA', XlsxWriter::colName(26));
        self::assertSame('AZ', XlsxWriter::colName(51));
        self::assertSame('BA', XlsxWriter::colName(52));
    }

    // ─── T-06: CSV injection ──────────────────────────────────────────────

    public function testCsvInjectionSanitized(): void
    {
        $report = $this->manager()->build('inject')
            ->dataset([
                ['cmd' => '=cmd|\' /C calc\'!A0'],
                ['cmd' => '+1234'],
                ['cmd' => '-2+3'],
                ['cmd' => '@SUM(A1)'],
                ['cmd' => 'safe text'],
            ])
            ->addField('cmd', label: 'command');

        $csv = $this->manager()->export($report, 'csv');

        // 🔒 هر سلول خطرناک با apostrophe prefix شده
        self::assertStringContainsString("'=cmd|", $csv);
        self::assertStringContainsString("'+1234", $csv);
        self::assertStringContainsString("'-2+3", $csv);
        self::assertStringContainsString("'@SUM(A1)", $csv);
        // متن سالم دست‌نخورده
        self::assertStringContainsString('safe text', $csv);
        self::assertTrue(!\str_contains($csv, "\n=cmd"));   // بدون prefix هرگز سر خط نمی‌آید
    }

    // ─── T-07: streaming ──────────────────────────────────────────────────

    public function testCsvStreamingLargeDataset(): void
    {
        $rows = [];
        for ($i = 0; $i < 10_000; $i++) {
            $rows[] = ['id' => $i, 'title' => 'ردیف ' . $i];
        }

        $report = $this->manager()->build('big')
            ->dataset($rows)
            ->addField('id', label: 'شناسه', type: 'number')
            ->addField('title', label: 'عنوان');

        $result = $report->run();
        self::assertSame(10_000, $result->count());

        // chunked — ژنراتور تکه‌ای
        $chunks = 0;
        $rowCount = 0;
        foreach ($result->chunked(1000) as $chunk) {
            $chunks++;
            self::assertLessThan(1001, \count($chunk));
            $rowCount += \count($chunk);
        }
        self::assertSame(10, $chunks);
        self::assertSame(10_000, $rowCount);

        // stream → php://temp (مسیر streaming واقعی)
        $handle = \fopen('php://temp', 'wb');
        self::assertNotNull($handle);
        $this->manager()->stream($report, 'csv', $handle);
        \rewind($handle);
        $content = (string) \stream_get_contents($handle);
        \fclose($handle);

        self::assertTrue(\str_starts_with($content, "\xEF\xBB\xBF"));
        self::assertCount(10_001, \explode("\n", \trim($content)));   // header + 10000
    }

    // ─── T-08: max_rows ───────────────────────────────────────────────────

    public function testMaxRowsLimitTruncates(): void
    {
        $this->app->make('config')->set('report.max_rows', 2);

        $rows = [];
        for ($i = 0; $i < 50; $i++) {
            $rows[] = ['n' => $i];
        }

        $result = $this->manager()->build('limited')->dataset($rows)->addField('n', type: 'number')->run();

        self::assertSame(2, $result->count());
        self::assertTrue($result->truncated());
    }

    public function testExplicitLimit(): void
    {
        $rows = [];
        for ($i = 0; $i < 50; $i++) {
            $rows[] = ['n' => $i];
        }

        $result = $this->manager()->build('limited')->dataset($rows)->addField('n', type: 'number')->limit(5)->run();
        self::assertSame(5, $result->count());
        self::assertTrue($result->truncated());
    }

    // ─── T-09: HTML render ────────────────────────────────────────────────

    public function testHtmlRenderEscapesAndFormats(): void
    {
        $output = $this->manager()->render($this->salesReport());

        $html = $output instanceof Response ? $output->body() : (string) $output;

        // ساختار
        self::assertStringContainsString('گزارش sales', $html);
        self::assertStringContainsString('<table', $html);
        // 🔒 XSS escape — هرگز raw بیرون نمی‌آید
        self::assertTrue(!\str_contains($html, '<script>alert(1)</script>'));
        self::assertStringContainsString('&lt;script&gt;', $html);
        // تاریخ جلالی
        self::assertStringContainsString('1405/07/25', $html);
        // جمع کل
        self::assertStringContainsString('جمع کل', $html);
    }

    public function testHtmlExportAsString(): void
    {
        $html = $this->manager()->export($this->salesReport(), 'html');
        self::assertStringContainsString('<!doctype html>', $html);
        self::assertStringContainsString('dir="rtl"', $html);
    }

    // ─── JSON / PDF / path ────────────────────────────────────────────────

    public function testJsonExport(): void
    {
        $json = $this->manager()->export($this->salesReport(), 'json');
        $decoded = \json_decode($json, true);

        self::assertNotNull($decoded);
        self::assertSame('sales', $decoded['name']);
        self::assertSame(['تاریخ', 'مبلغ', 'وضعیت', 'شهر'], $decoded['headers']);
        self::assertSame(5, $decoded['count']);
        self::assertSame(9_550_000, $decoded['totals']['total']);
        self::assertSame('1405/06/10', $decoded['rows'][0]['created_at']);
    }

    public function testPdfNotConfiguredThrows(): void
    {
        $this->expectException(ReportException::class);
        $this->expectExceptionMessageId('report.pdf_not_configured');
        $this->manager()->export($this->salesReport(), 'pdf');
    }

    public function testPdfHtmlDriverProducesPrintableHtml(): void
    {
        $this->app->make('config')->set('report.pdf_driver', 'html');

        $output = $this->manager()->export($this->salesReport(), 'pdf');
        self::assertStringContainsString('media="print"', $output);
        self::assertStringContainsString('@page', $output);
    }

    public function testUnknownFormatThrows(): void
    {
        $this->expectException(ReportException::class);
        $this->expectExceptionMessageId('report.unknown_format');
        $this->manager()->export($this->salesReport(), 'docx');
    }

    public function testExportToFileAndPathSandbox(): void
    {
        $path = \storage_path('temp/report-test-' . \getmypid() . '.xlsx');
        $written = $this->manager()->export($this->salesReport(), 'xlsx', path: $path);

        self::assertSame($path, $written);
        self::assertTrue(\is_file($path));
        self::assertTrue(\str_starts_with((string) \file_get_contents($path), "PK\x03\x04"));
        @\unlink($path);

        // 🔒 sandbox — مسیر بیرون از پروژه رد می‌شود
        try {
            $this->manager()->export($this->salesReport(), 'csv', path: '/tmp/evil-report.csv');
            self::fail('Expected path_not_allowed');
        } catch (ReportException $e) {
            self::assertSame('report.path_not_allowed', $e->messageId);
        }
    }

    public function testMissingDatasetThrows(): void
    {
        $this->expectException(ReportException::class);
        $this->expectExceptionMessageId('report.no_dataset');
        $this->manager()->build('empty')->addField('x')->run();
    }

    public function testUnknownAggregateThrows(): void
    {
        $this->expectException(ReportException::class);
        $this->expectExceptionMessageId('report.unknown_aggregate');
        $this->manager()->build('bad')->dataset([])->addField('x', aggregate: 'median');
    }

    public function testSqlDataset(): void
    {
        $this->seedDatabase();

        $report = $this->manager()->build('sql-sales')
            ->dataset('SELECT status, SUM(total) AS sum_total FROM tvn_test_sales GROUP BY status')
            ->addField('status', label: 'وضعیت')
            ->addField('sum_total', label: 'جمع', type: 'money');

        $result = $report->run();
        self::assertSame(3, $result->count());
    }
}

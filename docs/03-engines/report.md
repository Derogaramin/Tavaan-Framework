# DOC-072 — موتور Report | گزارش‌ساز

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-072 |
| Engine ID | `tavaan.engine.report` |
| Namespace | `Tavaan\Engine\Report` |
| اولویت | **P2** — فاز M7 |
| وابستگی | `database`, `filesystem`, `view` |
| Facade | `Report` |
| افزونه | `zip`(اختیاری برای xlsx)، fallback خالص |
| LOC | ۱۶۰۰ |

## ۱. هدف
گزارش‌ساز با Dataset، Filter، Group، Aggregate، Pivot و خروجی‌های CSV/Excel(xlsx
خودنوشته)/PDF(اختیاری)/JSON/HTML — برای داشبوردهای ERP/CRM/BPMS.

## ۲. مفاهیم
| واژه | تعریف |
|---|---|
| **Dataset** | منبع داده (Query/View/SQL) |
| **Field** | ستون گزارش |
| **Filter** | فیلتر پویا (بازهٔ تاریخ، وضعیت…) |
| **Group** | گروه‌بندی + subtotal |
| **Pivot** | جدول متقاطع |
| **Aggregate** | sum/avg/count/min/max |
| **Format** | خروجی (csv/xlsx/pdf/html) |

## ۳. API
```php
$report = Report::build('sales')
    ->dataset(fn () => DB::table('shop_orders')->select('created_at','total','status'))
    ->addField('created_at', label: 'تاریخ', type: 'date', format: 'jalali')
    ->addField('total', label: 'مبلغ', type: 'money', aggregate: 'sum')
    ->addField('status', label: 'وضعیت')
    ->addFilter('date_range', field: 'created_at', type: 'daterange')
    ->addFilter('status', field: 'status', type: 'select', options: OrderStatus::cases())
    ->groupBy('status', subtotal: ['total'=>'sum'])
    ->pivot(rows: 'status', columns: 'month', values: 'total', aggregate: 'sum')
    ->orderBy('total', 'desc');

$result = $report->run(filters: ['date_range'=>[$from,$to], 'status'=>'paid']);
$result->rows();       // داده
$result->totals();     // جمع کل
$result->subtotals();  // جمع جزء

// خروجی
Report::export($report, 'csv');          // → stream
Report::export($report, 'xlsx', path: storage_path('reports/sales.xlsx'));
Report::export($report, 'html');         // با view
Report::render($report);                 // در مرورگر
```

## ۴. xlsx Writer (خودنوشته، بدون PhpSpreadsheet)
```php
final class XlsxWriter
{
    public function write(array $rows, array $headers, string $path): void
    {
        $zip = new ZipWriter();          // از compat (خودنوشته یا ext-zip)
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStrings($rows, $headers));
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($rows, $headers));
        $zip->saveTo($path);
    }

    private function sheet(array $rows, array $headers): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        // header row
        $xml .= $this->row(1, $headers, isHeader: true);
        $r = 2;
        foreach ($rows as $row) { $xml .= $this->row($r++, $row); }
        return $xml . '</sheetData></worksheet>';
    }

    private function row(int $index, array $cells, bool $isHeader = false): string
    {
        $xml = '<row r="' . $index . '">';
        $col = 0;
        foreach ($cells as $value) {
            $ref = self::colName($col++) . $index;
            if (\is_numeric($value)) {
                $xml .= '<c r="' . $ref . '"><v>' . $value . '</v></c>';
            } else {
                $idx = $this->stringIndex((string) $value);
                $xml .= '<c r="' . $ref . '" t="s"' . ($isHeader ? ' s="1"' : '') . '><v>' . $idx . '</v></c>';
            }
        }
        return $xml . '</row>';
    }
}
```
🔒 `ZipWriter` از موتور `compat` (خالص PHP اگر ext-zip نبود).
🔒 xlsx = ZIP با ساختار OOXML استاندارد (قابل باز شدن در Excel/LibreOffice).

## ۵. CSV Export (Streaming برای دادهٔ بزرگ)
```php
public function toCsvStream(ReportResult $result): void
{
    $handle = \fopen('php://output', 'wb');
    \fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));   // 🔒 BOM برای UTF-8 در Excel

    \fputcsv($handle, $result->headers(), delimiter: ';');
    foreach ($result->chunked(1000) as $chunk) {
        foreach ($chunk as $row) { \fputcsv($handle, $row, delimiter: ';'); }
        \flush();
    }
    \fclose($handle);
}
```
🔒 BOM برای نمایش درست فارسی در Excel.
🔒 Streaming برای گزارش‌های بزرگ (حافظهٔ ثابت).
🔒 `;` به‌جای `,` (سازگاری با Excel فارسی).

## ۶. Pivot
```php
// rows: status, columns: month, values: sum(total)
final class PivotBuilder
{
    public function build(array $rows, PivotConfig $config): array
    {
        $columns = \array_unique(\array_column($rows, $config->columnField));
        \sort($columns);
        $pivot = [];
        foreach ($rows as $row) {
            $rk = $row[$config->rowField];
            $ck = $row[$config->columnField];
            $pivot[$rk][$ck] = $this->aggregate($pivot[$rk][$ck] ?? 0, $row[$config->valueField], $config->aggregate);
        }
        return ['columns'=>$columns, 'data'=>$pivot];
    }
}
```

## ۷. پیکربندی
```php
// config/report.php
return [
    'formats' => ['csv','xlsx','html','json','pdf'],
    'pdf_driver' => env_string('TVN_PDF_DRIVER', null),   // اختیاری (dompdf-like)
    'xlsx' => ['driver' => 'auto'],                        // auto|ext-zip|pure
    'max_rows' => 100_000,
    'streaming_threshold' => 5000,
    'csv' => ['delimiter'=>';', 'bom'=>true, 'enclosure'=>'"'],
    'temp_path' => storage_path('temp'),
];
```

## ۸. امنیت
| تهدید | کنترل |
|---|---|
| CSV Injection (formula) | prefix `'` برای سلول‌های `=+-@` |
| SQL Injection در dataset | Prepared + فیلترهای allowlist |
| DoS با گزارش بزرگ | max_rows + streaming |
| نشت داده | مجوز `report.view` + row-level scope |
| Path در export | sandbox |

```php
// 🔒 CSV Injection prevention
private function sanitizeCell(string $value): string
{
    if ($value !== '' && \str_contains('=+-@', $value[0])) {
        return "'" . $value;      // prefix با apostrophe
    }
    return $value;
}
```

## ۹. کارایی
| عملیات | هدف |
|---|---|
| Report ۱۰۰۰۰ ردیف (html) | ≤ ۲s |
| CSV streaming ۱۰۰۰۰۰ ردیف | حافظه ≤ ۵MB |
| xlsx ۱۰۰۰۰ ردیف | ≤ ۳s |
| Pivot aggregation | ≤ ۱s |

## ۱۰. تست
| T-01 | Dataset + fields + filters |
| T-02 | groupBy + subtotal |
| T-03 | pivot |
| T-04 | CSV export با BOM + delimiter |
| T-05 | xlsx export → قابل باز شدن |
| T-06 | CSV injection sanitize |
| T-07 | streaming حافظه ثابت |
| T-08 | max_rows limit |
| T-09 | HTML render با view |

## ۱۱. معیار پذیرش 🧪
- AC-RPT-01..09. AC-RPT-10: xlsx در Excel/LibreOffice/Google Sheets باز می‌شود.

## ۱۲. مراجع
| منبع | تفاوت |
|---|---|
| PhpSpreadsheet | xlsx | خودنوشته (بدون وابستگی)، فقط write |
| Laravel Excel | export | streaming، CSV injection safe |
| Metabase/Superset | BI concepts | سبک‌تر، embedded |

## ۱۳. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.report.v1
engine:
  id: tavaan.engine.report
  namespace: Tavaan\Engine\Report
  path: engines/report
  priority: P2
  phase: M7
  depends_on: [database, filesystem, view]
  extensions: {required: [], optional: [{name: zip, fallback: ZipWriterPure}]}
  facade: {name: Report, accessor: report}
  formats: [csv, xlsx, html, json, pdf]
  config_file: config/report.php
  public_api:
    - class: ReportBuilder
      methods: [dataset, addField, addFilter, groupBy, pivot, orderBy, run]
    - class: ReportExporter
      methods: [csv, xlsx, html, json, pdf, stream]
  loc_estimate: 1600
```

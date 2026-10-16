<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report\Export;

use Tavaan\Engine\Compat\ZipWriterPure;

/**
 * تَوان | Tavaan — Report
 * XlsxWriter — تولید xlsx (OOXML) کاملاً خودنوشته، بدون PhpSpreadsheet (DOC-072 §4).
 *
 * 🔒 ZIP با ZipWriterPure موتور compat (خالص PHP؛ بدون نیاز به ext-zip).
 * 🔒 خروجی در Excel/LibreOffice/Google Sheets باز می‌شود (AC-RPT-10).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class XlsxWriter
{
    /** @var array<string, int> رشته → ایندکس sharedStrings */
    private array $strings = [];

    /** @var list<string> */
    private array $stringList = [];

    /**
     * نوشتن فایل xlsx.
     *
     * @param list<array<int, mixed>> $rows    ردیف‌های داده (فهرست سلول‌ها)
     * @param list<string>            $headers سطر سربرگ
     */
    public function write(array $rows, array $headers, string $path, string $sheetName = 'Report'): void
    {
        $content = $this->toString($rows, $headers, $sheetName);
        if (\file_put_contents($path, $content) === false) {
            throw new \RuntimeException(\sprintf('Unable to write xlsx file: %s', $path));
        }
    }

    /**
     * ساخت محتوای xlsx به‌صورت رشتهٔ باینری.
     *
     * @param list<array<int, mixed>> $rows
     * @param list<string>            $headers
     */
    public function toString(array $rows, array $headers, string $sheetName = 'Report'): string
    {
        $this->strings = [];
        $this->stringList = [];

        // sheet پیش از sharedStrings ساخته می‌شود (ایندکس‌ها حین ساخت جمع می‌شوند)
        $sheet = $this->sheet($rows, $headers);

        $zip = new ZipWriterPure();
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/workbook.xml', $this->workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStrings());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);

        return $zip->output();
    }

    private function sheet(array $rows, array $headers): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if ($headers !== []) {
            $lastCol = self::colName(\max(0, \count($headers) - 1));
            $xml .= '<dimension ref="A1:' . $lastCol . (\count($rows) + 1) . '"/>';
        }
        $xml .= '<sheetViews><sheetView workbookViewId="0">'
            . ($headers !== [] ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' : '')
            . '</sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        if ($headers !== []) {
            $xml .= '<cols><col min="1" max="' . \count($headers) . '" width="18" customWidth="1"/></cols>';
        }
        $xml .= '<sheetData>';

        if ($headers !== []) {
            $xml .= $this->row(1, $headers, true);
        }
        $index = $headers !== [] ? 2 : 1;
        foreach ($rows as $row) {
            $xml .= $this->row($index++, \array_values($row));
        }

        return $xml . '</sheetData></worksheet>';
    }

    /**
     * @param list<mixed> $cells
     */
    private function row(int $index, array $cells, bool $isHeader = false): string
    {
        $xml = '<row r="' . $index . '">';
        $col = 0;
        foreach ($cells as $value) {
            $ref = self::colName($col++) . $index;
            if (\is_int($value) || \is_float($value)) {
                // عدد raw → سلول عددی (بدون quote)
                $number = \is_float($value) ? \rtrim(\rtrim(\sprintf('%.10F', $value), '0'), '.') : (string) $value;
                $xml .= '<c r="' . $ref . '"' . ($isHeader ? ' s="1"' : '') . '><v>' . $number . '</v></c>';
                continue;
            }

            $string = $value === null ? '' : (\is_scalar($value) ? (string) $value : (\json_encode($value, JSON_UNESCAPED_UNICODE) ?: ''));
            $string = CsvExporter::sanitizeCell($string);   // 🔒 محافظ formula در xlsx هم
            $xml .= '<c r="' . $ref . '" t="s"' . ($isHeader ? ' s="1"' : '') . '><v>' . $this->stringIndex($string) . '</v></c>';
        }

        return $xml . '</row>';
    }

    private function stringIndex(string $value): int
    {
        if (!isset($this->strings[$value])) {
            $this->strings[$value] = \count($this->stringList);
            $this->stringList[] = $value;
        }

        return $this->strings[$value];
    }

    /** ستون عددی → نام (0→A, 25→Z, 26→AA). */
    public static function colName(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = \chr(65 + $mod) . $name;
            $index = \intdiv($index - 1, 26);
        }

        return $name;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '</Types>';
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->escape($this->sheetName($sheetName)) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>';
    }

    /** styles: فونت عادی + Bold (سربرگ s="1"). */
    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="12"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="12"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8EAF0"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function sharedStrings(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'
            . \count($this->stringList) . '" uniqueCount="' . \count($this->stringList) . '">';
        foreach ($this->stringList as $string) {
            $xml .= '<si><t xml:space="preserve">' . $this->escape($this->stripInvalidXml($string)) . '</t></si>';
        }

        return $xml . '</sst>';
    }

    private function escape(string $value): string
    {
        return \htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** 🔒 حذف کاراکترهای کنترل نامعتبر برای XML 1.0. */
    private function stripInvalidXml(string $value): string
    {
        return (string) \preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);
    }

    private function sheetName(string $name): string
    {
        // نام sheet در xlsx: ≤31 کاراکتر، بدون : \ / ? * [ ]
        $clean = \str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $name);
        $clean = \trim($clean) === '' ? 'Report' : \trim($clean);

        return \mb_substr($clean, 0, 31);
    }
}

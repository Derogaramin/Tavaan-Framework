<?php

declare(strict_types=1);

/*
 * ماژول ERP — seed دمو (DOC-097؛ kit:install erp --with-demo).
 *
 * دادهٔ نمایشی: chart of accounts استاندارد (۵ گروه)، دورهٔ مالی باز جاری،
 * ۲ انبار، ۶ کالا (تجاری/مادهٔ اولیه/محصول)، ۲ تأمین‌کننده، ۲ مشتری،
 * ۳ پرسنل + کارکرد ماه جاری و یک BOM فعال.
 * idempotent — با کلید موجود، رد می‌شود.
 */

use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Support\Ulid;

return static function (): void {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $fmt = 'Y-m-d H:i:s.v';

    // ── chart of accounts (DOC-097 §2.1 — استاندارد حسابداری ایران) ──────
    if ((int) DB::table('erp_accounts')->count() === 0) {
        $accounts = [
            // [code, title, type, parent_code, level, is_leaf]
            ['1', 'دارایی‌ها', 'asset', null, 1, false],
            ['11', 'موجودی نقد و بانک', 'asset', '1', 2, false],
            ['1101', 'صندوق', 'asset', '11', 3, true],
            ['1102', 'بانک', 'asset', '11', 3, true],
            ['12', 'حساب‌های دریافتنی', 'asset', '1', 2, false],
            ['1201', 'حساب‌های دریافتنی تجاری', 'asset', '12', 3, true],
            ['13', 'موجودی کالا', 'asset', '1', 2, false],
            ['1301', 'کالای تجاری', 'asset', '13', 3, true],
            ['1302', 'مواد اولیه', 'asset', '13', 3, true],
            ['1303', 'کالای در جریان ساخت', 'asset', '13', 3, true],
            ['1304', 'محصول ساخته‌شده', 'asset', '13', 3, true],
            ['2', 'بدهی‌ها', 'liability', null, 1, false],
            ['21', 'حساب‌های پرداختنی', 'liability', '2', 2, false],
            ['2101', 'حساب‌های پرداختنی تجاری', 'liability', '21', 3, true],
            ['22', 'حقوق پرداختنی', 'liability', '2', 2, false],
            ['2201', 'حقوق و دستمزد پرداختنی', 'liability', '22', 3, true],
            ['3', 'حقوق صاحبان سهام', 'equity', null, 1, false],
            ['31', 'سرمایه', 'equity', '3', 2, false],
            ['3101', 'سرمایهٔ ثبت‌شده', 'equity', '31', 3, true],
            ['32', 'خلاصهٔ سود و زیان', 'equity', '3', 2, false],
            ['3201', 'خلاصهٔ سود و زیان', 'equity', '32', 3, true],
            ['33', 'سود انباشته', 'equity', '3', 2, false],
            ['3301', 'سود (زیان) انباشته', 'equity', '33', 3, true],
            ['4', 'درآمدها', 'revenue', null, 1, false],
            ['41', 'درآمد فروش', 'revenue', '4', 2, false],
            ['4101', 'درآمد فروش کالا', 'revenue', '41', 3, true],
            ['5', 'هزینه‌ها', 'expense', null, 1, false],
            ['51', 'بهای کالای فروش‌رفته', 'expense', '5', 2, false],
            ['5101', 'بهای کالای فروش‌رفته', 'expense', '51', 3, true],
            ['52', 'هزینهٔ حقوق', 'expense', '5', 2, false],
            ['5201', 'هزینهٔ حقوق و دستمزد', 'expense', '52', 3, true],
            ['53', 'سایر هزینه‌ها', 'expense', '5', 2, false],
            ['5301', 'سایر هزینه‌های عملیاتی', 'expense', '53', 3, true],
        ];

        $idByCode = [];
        foreach ($accounts as [$code, $title, $type, $parentCode, $level, $isLeaf]) {
            $id = DB::table('erp_accounts')->insertGetId([
                'code' => $code,
                'title' => $title,
                'parent_id' => $parentCode !== null ? ($idByCode[$parentCode] ?? null) : null,
                'type' => $type,
                'level' => $level,
                'is_leaf' => $isLeaf,
                'normal_balance' => in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit',
                'created_at' => $now->format($fmt),
                'updated_at' => null,
            ]);
            $idByCode[$code] = $id;
        }
    }

    // ── دورهٔ مالی باز (سال جاری میلادی) ──────────────────────────────────
    $year = (int) $now->format('Y');
    if ((int) DB::table('erp_fiscal_periods')->count() === 0) {
        DB::table('erp_fiscal_periods')->insert([
            'title' => 'سال مالی ' . $year,
            'start_date' => $year . '-01-01',
            'end_date' => $year . '-12-31',
            'status' => 'open',
            'close_voucher_id' => null,
            'closed_by' => null,
            'closed_at' => null,
            'created_at' => $now->format($fmt),
            'updated_at' => null,
        ]);
    }

    // ── انبارها ────────────────────────────────────────────────────────────
    $warehouses = [];
    foreach ([['WH-CEN', 'انبار مرکزی', 'تهران — شهرک صنعتی'], ['WH-SHOP', 'انبار فروشگاه', 'تهران — بازار']] as [$code, $name, $address]) {
        $row = DB::table('erp_warehouses')->where('code', $code)->first();
        if ($row !== null) {
            $warehouses[$code] = (string) $row['id'];

            continue;
        }
        $id = Ulid::generate($now)->toString();
        DB::table('erp_warehouses')->insert([
            'id' => $id, 'code' => $code, 'name' => $name, 'address' => $address,
            'created_at' => $now->format($fmt), 'updated_at' => null,
        ]);
        $warehouses[$code] = $id;
    }

    // ── کالاها ─────────────────────────────────────────────────────────────
    $items = [];
    foreach ([
        ['SKU-1001', 'لپ‌تاپ صنعتی T-Book', 'عدد', 'merchandise', 48_500_000, 2.0],
        ['SKU-1002', 'ماوس بی‌سیم M-Pro', 'عدد', 'merchandise', 850_000, 10.0],
        ['SKU-1003', 'کیبورد مکانیکی K-Type', 'عدد', 'merchandise', 3_200_000, 5.0],
        ['SKU-2001', 'برد الکترونیکی خام', 'عدد', 'raw_material', 1_100_000, 20.0],
        ['SKU-2002', 'قاب آلومینیومی', 'عدد', 'raw_material', 620_000, 20.0],
        ['SKU-3001', 'دستگاه کنترل دسترسی D-1', 'عدد', 'finished', 0, 0.0],
    ] as [$sku, $name, $unit, $kind, $avgCost, $reorderPoint]) {
        $row = DB::table('erp_inventory_items')->where('sku', $sku)->first();
        if ($row !== null) {
            $items[$sku] = (string) $row['id'];

            continue;
        }
        $id = Ulid::generate($now)->toString();
        DB::table('erp_inventory_items')->insert([
            'id' => $id, 'sku' => $sku, 'name' => $name, 'unit' => $unit,
            'reorder_point' => $reorderPoint, 'reorder_qty' => $reorderPoint > 0 ? $reorderPoint * 2 : 0,
            'valuation_method' => 'weighted_avg', 'avg_cost' => $avgCost, 'kind' => $kind,
            'is_sold' => $kind !== 'raw_material',
            'created_at' => $now->format($fmt), 'updated_at' => null,
        ]);
        $items[$sku] = $id;
    }

    // ── تأمین‌کنندگان ──────────────────────────────────────────────────────
    foreach ([
        ['SUP-01', 'فناوری پارس‌تک', '021-88776655', 'sales@parstech.test'],
        ['SUP-02', 'صنایع فلزی آریا', '021-44332211', 'info@aria-metal.test'],
    ] as [$code, $name, $phone, $email]) {
        if (DB::table('erp_suppliers')->where('code', $code)->count() > 0) {
            continue;
        }
        DB::table('erp_suppliers')->insert([
            'id' => Ulid::generate($now)->toString(), 'code' => $code, 'name' => $name,
            'phone' => $phone, 'email' => $email, 'address' => null,
            'created_at' => $now->format($fmt), 'updated_at' => null,
        ]);
    }

    // ── مشتریان ────────────────────────────────────────────────────────────
    foreach ([
        ['CUS-01', 'بازرگانی نگین', '021-22334455', 'buy@negin.test'],
        ['CUS-02', 'استارتاپ سیمرغ', '021-66778899', 'proc@simorgh.test'],
    ] as [$code, $name, $phone, $email]) {
        if (DB::table('erp_customers')->where('code', $code)->count() > 0) {
            continue;
        }
        DB::table('erp_customers')->insert([
            'id' => Ulid::generate($now)->toString(), 'code' => $code, 'name' => $name,
            'phone' => $phone, 'email' => $email, 'address' => null,
            'created_at' => $now->format($fmt), 'updated_at' => null,
        ]);
    }

    // ── BOM برای محصول D-1 ─────────────────────────────────────────────────
    $productId = $items['SKU-3001'] ?? null;
    if ($productId !== null && (int) DB::table('erp_bom')->where('product_id', $productId)->where('is_active', true)->count() === 0) {
        DB::table('erp_bom')->insert([
            'id' => Ulid::generate($now)->toString(),
            'product_id' => $productId,
            'version' => 1,
            'components' => (string) json_encode([
                ['item_id' => $items['SKU-2001'], 'qty' => 2.0],
                ['item_id' => $items['SKU-2002'], 'qty' => 1.0],
            ], JSON_UNESCAPED_UNICODE),
            'is_active' => true,
            'created_at' => $now->format($fmt),
            'updated_at' => null,
        ]);
    }

    // ── پرسنل ──────────────────────────────────────────────────────────────
    $employees = [];
    foreach ([
        ['EMP-001', 'مریم نوری', 'مدیر مالی', 185_000_000],
        ['EMP-002', 'رضا کریمی', 'انباردار', 92_000_000],
        ['EMP-003', 'نگار موسوی', 'کارشناس فروش', 105_000_000],
    ] as [$code, $name, $position, $salary]) {
        $row = DB::table('erp_employees')->where('employee_code', $code)->first();
        if ($row !== null) {
            $employees[$code] = (string) $row['id'];

            continue;
        }
        $id = Ulid::generate($now)->toString();
        DB::table('erp_employees')->insert([
            'id' => $id, 'user_id' => null, 'employee_code' => $code, 'name' => $name,
            'position' => $position, 'base_salary' => $salary,
            'national_id' => null,   // دادهٔ دمو — بدون کد ملی
            'hire_date' => ($year - 2) . '-04-01', 'status' => 'active',
            'created_at' => $now->format($fmt), 'updated_at' => null,
        ]);
        $employees[$code] = $id;
    }

    // ── کارکرد ماه جاری ────────────────────────────────────────────────────
    $month = $now->format('Y-m');
    foreach ($employees as $index => $employeeId) {
        $exists = DB::table('erp_attendance')
            ->where('employee_id', $employeeId)
            ->where('period_month', $month)
            ->count() > 0;
        if ($exists) {
            continue;
        }
        DB::table('erp_attendance')->insert([
            'employee_id' => $employeeId,
            'period_month' => $month,
            'work_hours' => 176.0,
            'overtime_hours' => $index === 0 ? 0.0 : 12.5,
            'leave_days' => $index === 1 ? 1.0 : 0.0,
            'deductions' => 0,
        ]);
    }
};

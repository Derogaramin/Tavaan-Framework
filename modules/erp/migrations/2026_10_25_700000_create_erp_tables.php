<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول ماژول ERP — DOC-097 §2.
 *
 * 🔒 پول: int minor-unit (ADR-0013 — انطباق با DECIMAL(18,4) مستند در گزارش
 * M9 گام ۷ ثبت شده است). مقدارها (quantity): decimal(18,3).
 * prefix ماژول = erp_ (DoD §3).
 *
 * افزودنی‌های مستند نسبت به DOC-097 §2:
 * - erp_fiscal_periods: دورهٔ مالی (§4 — بستن حساب)
 * - erp_suppliers / erp_customers: اشخاص تجاری (E2E-01/02)
 * - erp_purchase_order_items / erp_goods_receipts / erp_supplier_invoices /
 *   erp_payments / erp_sales_* / erp_payslips / erp_attendance /
 *   erp_payroll_runs / erp_employees: جداول جریان‌های §2.3-2.5
 * - vouchers.period_id: قفل سند در دورهٔ بسته
 */

return new class () extends Migration {
    public function up(): void
    {
        // ── حسابداری (§2.1) ────────────────────────────────────────────────
        if (!Schema::hasTable('erp_accounts')) {
            Schema::create('erp_accounts', function (Blueprint $t): void {
                $t->id();
                $t->string('code', 50)->unique();
                $t->string('title', 190);
                $t->unsignedBigInteger('parent_id')->nullable();
                $t->string('type', 20);                       // asset|liability|equity|revenue|expense
                $t->integer('level')->default(1);
                $t->boolean('is_leaf')->default(true);
                $t->string('normal_balance', 10);              // debit|credit
                $t->timestamps(3);
                $t->index(['parent_id'], 'idx_erp_account_parent');
                $t->index(['type'], 'idx_erp_account_type');
            });
        }

        if (!Schema::hasTable('erp_fiscal_periods')) {
            Schema::create('erp_fiscal_periods', function (Blueprint $t): void {
                $t->id();
                $t->string('title', 100);
                $t->date('start_date');
                $t->date('end_date');
                $t->string('status', 20)->default('open');     // open|closed
                $t->char('close_voucher_id', 26)->nullable();
                $t->unsignedBigInteger('closed_by')->nullable();
                $t->dateTime('closed_at', 3)->nullable();
                $t->timestamps(3);
            });
        }

        if (!Schema::hasTable('erp_vouchers')) {
            Schema::create('erp_vouchers', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                // ULID
                $t->string('voucher_number', 50)->unique();
                $t->unsignedBigInteger('period_id')->nullable();
                $t->date('date');
                $t->text('description')->nullable();
                $t->string('status', 20)->default('draft');    // draft|approved|posted|voided
                $t->string('source_module', 50)->default('manual');   // manual|procurement|sales|inventory|production|hr|closing
                $t->char('source_id', 26)->nullable();
                $t->char('reverse_of', 26)->nullable();        // سند معکوس (void)
                $t->bigInteger('total_debit')->default(0);     // minor-unit
                $t->bigInteger('total_credit')->default(0);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->dateTime('posted_at', 3)->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->index(['date'], 'idx_erp_voucher_date');
                $t->index(['source_module', 'source_id'], 'idx_erp_voucher_source');
                $t->index(['status'], 'idx_erp_voucher_status');
            });
        }

        if (!Schema::hasTable('erp_voucher_lines')) {
            Schema::create('erp_voucher_lines', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('voucher_id', 26);
                $t->unsignedBigInteger('account_id');
                $t->bigInteger('debit')->default(0);           // minor-unit
                $t->bigInteger('credit')->default(0);
                $t->string('description', 255)->nullable();
                $t->unsignedBigInteger('cost_center_id')->nullable();
                $t->char('project_id', 26)->nullable();
                $t->index(['voucher_id'], 'idx_erp_line_voucher');
                $t->index(['account_id'], 'idx_erp_line_account');
            });
        }

        // ── انبار (§2.2) ───────────────────────────────────────────────────
        if (!Schema::hasTable('erp_warehouses')) {
            Schema::create('erp_warehouses', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('code', 50)->unique();
                $t->string('name', 190);
                $t->string('address', 255)->nullable();
                $t->timestamps(3);
            });
        }

        if (!Schema::hasTable('erp_inventory_items')) {
            Schema::create('erp_inventory_items', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('sku', 64)->unique();
                $t->string('name', 255);
                $t->string('unit', 20)->default('عدد');
                $t->decimal('reorder_point', 18, 3)->default(0);
                $t->decimal('reorder_qty', 18, 3)->default(0);
                $t->string('valuation_method', 20)->default('weighted_avg');
                $t->bigInteger('avg_cost')->default(0);        // minor-unit per unit (weighted avg)
                $t->string('kind', 20)->default('merchandise'); // merchandise|raw_material|finished
                $t->boolean('is_sold')->default(true);
                $t->timestamps(3);
            });
        }

        if (!Schema::hasTable('erp_stock')) {
            Schema::create('erp_stock', function (Blueprint $t): void {
                $t->id();
                $t->char('warehouse_id', 26);
                $t->char('item_id', 26);
                $t->decimal('quantity', 18, 3)->default(0);
                $t->decimal('reserved', 18, 3)->default(0);
                $t->unique(['warehouse_id', 'item_id'], 'uk_erp_stock');
            });
        }

        if (!Schema::hasTable('erp_stock_movements')) {
            Schema::create('erp_stock_movements', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('warehouse_id', 26);
                $t->char('item_id', 26);
                $t->string('type', 20);                        // receipt|issue|transfer_in|transfer_out|adjustment
                $t->decimal('quantity', 18, 3);                // همیشه مثبت — جهت از type
                $t->bigInteger('unit_cost')->default(0);       // minor-unit
                $t->string('reference_type', 50)->nullable();  // purchase_receipt|sales_order|work_order|adjustment|transfer|opening
                $t->char('reference_id', 26)->nullable();
                $t->char('voucher_id', 26)->nullable();        // سند حسابداری مرتبط
                $t->unsignedBigInteger('user_id')->nullable();
                $t->dateTime('created_at', 3);
                $t->index(['item_id', 'created_at'], 'idx_erp_mov_item');
                $t->index(['warehouse_id'], 'idx_erp_mov_wh');
                $t->index(['reference_type', 'reference_id'], 'idx_erp_mov_ref');
            });
        }

        // ── اشخاص تجاری ────────────────────────────────────────────────────
        if (!Schema::hasTable('erp_suppliers')) {
            Schema::create('erp_suppliers', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('code', 50)->unique();
                $t->string('name', 190);
                $t->string('phone', 20)->nullable();
                $t->string('email', 190)->nullable();
                $t->string('address', 255)->nullable();
                $t->timestamps(3);
            });
        }

        if (!Schema::hasTable('erp_customers')) {
            Schema::create('erp_customers', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('code', 50)->unique();
                $t->string('name', 190);
                $t->string('phone', 20)->nullable();
                $t->string('email', 190)->nullable();
                $t->string('address', 255)->nullable();
                $t->timestamps(3);
            });
        }

        // ── تدارکات (§2.3) ─────────────────────────────────────────────────
        if (!Schema::hasTable('erp_purchase_orders')) {
            Schema::create('erp_purchase_orders', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('po_number', 50)->unique();
                $t->char('supplier_id', 26);
                $t->string('status', 20)->default('draft');    // draft|pending_finance|pending_ceo|approved|rejected|received|invoiced|paid|canceled
                $t->bigInteger('total')->default(0);           // minor-unit
                $t->string('currency', 3)->default('IRR');
                $t->string('workflow_instance_id', 64)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('finance_approved_by')->nullable();
                $t->unsignedBigInteger('ceo_approved_by')->nullable();
                $t->dateTime('approved_at', 3)->nullable();
                $t->string('reject_reason', 255)->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->index(['status'], 'idx_erp_po_status');
                $t->index(['supplier_id'], 'idx_erp_po_supplier');
            });
        }

        if (!Schema::hasTable('erp_purchase_order_items')) {
            Schema::create('erp_purchase_order_items', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('po_id', 26);
                $t->char('item_id', 26);
                $t->decimal('qty', 18, 3);
                $t->decimal('received_qty', 18, 3)->default(0);
                $t->bigInteger('unit_price')->default(0);      // minor-unit
                $t->index(['po_id'], 'idx_erp_po_item_po');
            });
        }

        if (!Schema::hasTable('erp_goods_receipts')) {
            Schema::create('erp_goods_receipts', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('receipt_number', 50)->unique();
                $t->char('po_id', 26)->nullable();
                $t->char('supplier_id', 26)->nullable();
                $t->char('warehouse_id', 26);
                $t->json('items');                              // [{item_id, qty, unit_cost}]
                $t->unsignedBigInteger('received_by')->nullable();
                $t->bigInteger('total_cost')->default(0);      // minor-unit (جمع ردیف‌ها)
                $t->char('voucher_id', 26)->nullable();
                $t->dateTime('created_at', 3);
                $t->index(['po_id'], 'idx_erp_receipt_po');
            });
        }

        if (!Schema::hasTable('erp_supplier_invoices')) {
            Schema::create('erp_supplier_invoices', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('invoice_number', 50)->unique();
                $t->char('supplier_id', 26);
                $t->char('po_id', 26)->nullable();
                $t->char('receipt_id', 26)->nullable();
                $t->bigInteger('amount')->default(0);          // minor-unit
                $t->string('status', 20)->default('open');      // open|paid
                $t->dateTime('issued_at', 3);
                $t->dateTime('paid_at', 3)->nullable();
                $t->index(['supplier_id', 'status'], 'idx_erp_sinv_supplier');
            });
        }

        if (!Schema::hasTable('erp_payments')) {
            Schema::create('erp_payments', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('direction', 10);                   // out|in
                $t->char('supplier_id', 26)->nullable();
                $t->char('customer_id', 26)->nullable();
                $t->char('supplier_invoice_id', 26)->nullable();
                $t->char('sales_invoice_id', 26)->nullable();
                $t->bigInteger('amount')->default(0);           // minor-unit
                $t->string('method', 20)->default('bank');      // cash|bank
                $t->char('voucher_id', 26)->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->dateTime('paid_at', 3);
                $t->index(['supplier_id'], 'idx_erp_pay_supplier');
                $t->index(['customer_id'], 'idx_erp_pay_customer');
            });
        }

        // ── فروش ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('erp_sales_orders')) {
            Schema::create('erp_sales_orders', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('order_number', 50)->unique();
                $t->char('customer_id', 26);
                $t->char('warehouse_id', 26);
                $t->string('status', 20)->default('draft');     // draft|confirmed|delivered|invoiced|canceled
                $t->bigInteger('total')->default(0);            // minor-unit
                $t->unsignedBigInteger('created_by')->nullable();
                $t->dateTime('delivered_at', 3)->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->index(['status'], 'idx_erp_so_status');
            });
        }

        if (!Schema::hasTable('erp_sales_order_items')) {
            Schema::create('erp_sales_order_items', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('order_id', 26);
                $t->char('item_id', 26);
                $t->decimal('qty', 18, 3);
                $t->decimal('delivered_qty', 18, 3)->default(0);
                $t->bigInteger('unit_price')->default(0);       // minor-unit
                $t->bigInteger('unit_cost_snapshot')->default(0);  // COGS snapshot لحظهٔ حواله
                $t->index(['order_id'], 'idx_erp_so_item_order');
            });
        }

        if (!Schema::hasTable('erp_sales_invoices')) {
            Schema::create('erp_sales_invoices', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('invoice_number', 50)->unique();
                $t->char('customer_id', 26);
                $t->char('sales_order_id', 26)->nullable();
                $t->bigInteger('amount')->default(0);           // minor-unit
                $t->bigInteger('cogs_amount')->default(0);
                $t->string('status', 20)->default('issued');     // issued|paid|canceled
                $t->char('voucher_id', 26)->nullable();
                $t->dateTime('issued_at', 3);
                $t->dateTime('paid_at', 3)->nullable();
                $t->index(['customer_id', 'status'], 'idx_erp_sinv2_customer');
            });
        }

        // ── تولید (§2.4) ───────────────────────────────────────────────────
        if (!Schema::hasTable('erp_bom')) {
            Schema::create('erp_bom', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('product_id', 26);
                $t->integer('version')->default(1);
                $t->json('components');                         // [{item_id, qty}]
                $t->boolean('is_active')->default(true);
                $t->timestamps(3);
                $t->index(['product_id', 'is_active'], 'idx_erp_bom_product');
            });
        }

        if (!Schema::hasTable('erp_work_orders')) {
            Schema::create('erp_work_orders', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('wo_number', 50)->unique();
                $t->char('product_id', 26);
                $t->char('bom_id', 26);
                $t->char('warehouse_id', 26);                   // انبار مواد/محصول
                $t->decimal('planned_qty', 18, 3);
                $t->decimal('produced_qty', 18, 3)->default(0);
                $t->bigInteger('material_cost')->default(0);    // minor-unit (بهای تمام‌شده)
                $t->bigInteger('unit_cost')->default(0);        // minor-unit per produced unit
                $t->string('status', 20)->default('planned');   // planned|in_progress|completed|canceled
                $t->char('voucher_id', 26)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->dateTime('started_at', 3)->nullable();
                $t->dateTime('completed_at', 3)->nullable();
                $t->dateTime('created_at', 3);
                $t->index(['status'], 'idx_erp_wo_status');
            });
        }

        // ── حقوق و دستمزد (§2.5) ───────────────────────────────────────────
        if (!Schema::hasTable('erp_employees')) {
            Schema::create('erp_employees', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('employee_code', 50)->unique();
                $t->string('name', 190);
                $t->string('position', 100)->nullable();
                $t->bigInteger('base_salary')->default(0);      // minor-unit ماهانه
                $t->string('national_id', 500)->nullable();     // 🔒 encrypted (الگوی CRM)
                $t->date('hire_date')->nullable();
                $t->string('status', 20)->default('active');     // active|terminated
                $t->timestamps(3);
            });
        }

        if (!Schema::hasTable('erp_attendance')) {
            Schema::create('erp_attendance', function (Blueprint $t): void {
                $t->id();
                $t->char('employee_id', 26);
                $t->string('period_month', 7);                   // YYYY-MM
                $t->decimal('work_hours', 8, 2)->default(0);
                $t->decimal('overtime_hours', 8, 2)->default(0);
                $t->decimal('leave_days', 8, 2)->default(0);
                $t->bigInteger('deductions')->default(0);        // minor-unit (کسورات دستی)
                $t->unique(['employee_id', 'period_month'], 'uk_erp_attendance');
            });
        }

        if (!Schema::hasTable('erp_payroll_runs')) {
            Schema::create('erp_payroll_runs', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('period_month', 7);                   // YYYY-MM
                $t->string('status', 20)->default('draft');      // draft|processed|paid
                $t->bigInteger('total_gross')->default(0);
                $t->bigInteger('total_net')->default(0);
                $t->unsignedInteger('slip_count')->default(0);
                $t->char('voucher_id', 26)->nullable();
                $t->unsignedBigInteger('processed_by')->nullable();
                $t->dateTime('processed_at', 3)->nullable();
                $t->dateTime('created_at', 3);
                $t->unique(['period_month', 'status'], 'uk_erp_payroll_period');
            });
        }

        if (!Schema::hasTable('erp_payslips')) {
            Schema::create('erp_payslips', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('payroll_run_id', 26);
                $t->char('employee_id', 26);
                $t->string('period_month', 7);
                $t->bigInteger('base')->default(0);
                $t->bigInteger('overtime')->default(0);
                $t->bigInteger('deductions')->default(0);
                $t->bigInteger('tax')->default(0);
                $t->bigInteger('net')->default(0);
                $t->index(['payroll_run_id'], 'idx_erp_payslip_run');
                $t->unique(['payroll_run_id', 'employee_id'], 'uk_erp_payslip');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_payslips');
        Schema::dropIfExists('erp_payroll_runs');
        Schema::dropIfExists('erp_attendance');
        Schema::dropIfExists('erp_employees');
        Schema::dropIfExists('erp_work_orders');
        Schema::dropIfExists('erp_bom');
        Schema::dropIfExists('erp_sales_invoices');
        Schema::dropIfExists('erp_sales_order_items');
        Schema::dropIfExists('erp_sales_orders');
        Schema::dropIfExists('erp_payments');
        Schema::dropIfExists('erp_supplier_invoices');
        Schema::dropIfExists('erp_goods_receipts');
        Schema::dropIfExists('erp_purchase_order_items');
        Schema::dropIfExists('erp_purchase_orders');
        Schema::dropIfExists('erp_customers');
        Schema::dropIfExists('erp_suppliers');
        Schema::dropIfExists('erp_stock_movements');
        Schema::dropIfExists('erp_stock');
        Schema::dropIfExists('erp_inventory_items');
        Schema::dropIfExists('erp_warehouses');
        Schema::dropIfExists('erp_voucher_lines');
        Schema::dropIfExists('erp_vouchers');
        Schema::dropIfExists('erp_fiscal_periods');
        Schema::dropIfExists('erp_accounts');
    }
};

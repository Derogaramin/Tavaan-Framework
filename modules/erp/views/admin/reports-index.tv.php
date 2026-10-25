@extends('erp::layouts.admin')

@section('title', 'گزارش‌های مالی')

@section('content')
<div class="topline"><h1>📈 گزارش‌های مالی</h1></div>

<div class="card">
    <table class="data">
        <tr><th>گزارش</th><th>بافته</th><th></th><th>CSV</th></tr>
        <tr>
            <td>تراز آزمایشی</td><td class="muted">accounting</td>
            <td><a class="btn sm ghost" href="/admin/erp/reports/trial-balance">مشاهده</a></td>
            <td><a class="btn sm ghost" href="/admin/erp/reports/trial-balance.csv">⬇</a></td>
        </tr>
        <tr>
            <td>صورت سود و زیان</td><td class="muted">accounting</td>
            <td><a class="btn sm ghost" href="/admin/erp/reports/profit-loss">مشاهده</a></td>
            <td><a class="btn sm ghost" href="/admin/erp/reports/profit-loss.csv">⬇</a></td>
        </tr>
        <tr>
            <td>ترازنامه</td><td class="muted">accounting</td>
            <td><a class="btn sm ghost" href="/admin/erp/reports/balance-sheet">مشاهده</a></td>
            <td><a class="btn sm ghost" href="/admin/erp/reports/balance-sheet.csv">⬇</a></td>
        </tr>
        <tr>
            <td>گردش کالا</td><td class="muted">inventory</td>
            <td><a class="btn sm ghost" href="/admin/erp/reports/turnover">مشاهده</a></td>
            <td><a class="btn sm ghost" href="/admin/erp/reports/turnover.csv">⬇</a></td>
        </tr>
        <tr>
            <td>بهای تمام‌شدهٔ تولید</td><td class="muted">production</td>
            <td><a class="btn sm ghost" href="/admin/erp/reports/production-cost">مشاهده</a></td>
            <td>—</td>
        </tr>
        <tr>
            <td>حساب مشتری/تأمین‌کننده</td><td class="muted">sales/procurement</td>
            <td><a class="btn sm ghost" href="/admin/erp/reports/party">مشاهده</a></td>
            <td>—</td>
        </tr>
        <tr>
            <td>حقوق و دستمزد</td><td class="muted">hr</td>
            <td><a class="btn sm ghost" href="/admin/erp/reports/payroll">مشاهده</a></td>
            <td><a class="btn sm ghost" href="/admin/erp/reports/payroll.csv">⬇</a></td>
        </tr>
    </table>
    <p class="muted" style="margin-top:10px">همهٔ گزارش‌ها فقط از اسناد <b>posted</b> خوانده می‌شوند — مبالغ int minor-unit (ADR-0013).</p>
</div>
@endsection

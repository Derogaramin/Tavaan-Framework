@extends('erp::layouts.admin')

@section('title', 'حقوق و دستمزد')

@section('content')
<div class="topline">
    <h1>👷 منابع انسانی و حقوق</h1>
    <span class="spacer"></span>
    <form method="get" class="inline">
        <input type="text" name="month" value="{{ $month }}" placeholder="YYYY-MM" pattern="\d{4}-(0[1-9]|1[0-2])">
        <button class="btn sm ghost" type="submit">برو</button>
    </form>
</div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">پرسنل و کارکرد {{ $month }}</h2>
    <table class="data">
        <tr><th>کد</th><th>نام</th><th>سمت</th><th>حقوق پایه</th><th>ساعت کار</th><th>اضافه‌کاری</th><th>مرخصی</th><th>کسورات</th></tr>
        @forelse($employees as $employee)
            @php $att = $attendance[$employee->id()] ?? null; @endphp
            <tr>
                <td>{{ $employee->employeeCode() }}</td>
                <td>{{ $employee->name() }}</td>
                <td>{{ $employee->position() ?? '—' }}</td>
                <td class="num">{{ trans_number($employee->baseSalary()) }}</td>
                <td class="num">{{ $att !== null ? trans_number($att->workHours()) : '—' }}</td>
                <td class="num">{{ $att !== null ? trans_number($att->overtimeHours()) : '—' }}</td>
                <td class="num">{{ $att !== null ? trans_number($att->leaveDays()) : '—' }}</td>
                <td class="num">{{ $att !== null ? trans_number($att->deductions()) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">پرسنلی ثبت نشده است.</td></tr>
        @endforelse
    </table>

    <div class="section-box" style="margin-top:12px">
        <h3 style="font-size:.9rem;margin-bottom:8px">ثبت کارکرد ماهانه</h3>
        <form method="post" action="/admin/erp/hr/attendance">
            @csrf
            <input type="hidden" name="period_month" value="{{ $month }}">
            <div class="form-grid">
                <label>پرسنل
                    <select name="employee_id" required>
                        @foreach($employees as $employee)<option value="{{ $employee->id() }}">{{ $employee->employeeCode() }} — {{ $employee->name() }}</option>@endforeach
                    </select>
                </label>
                <label>ساعت کار<input type="number" name="work_hours" min="0" step="0.5" required value="176"></label>
                <label>ساعت اضافه‌کاری<input type="number" name="overtime_hours" min="0" step="0.5" value="0"></label>
                <label>روز مرخصی<input type="number" name="leave_days" min="0" step="0.5" value="0"></label>
                <label>کسورات (ریال)<input type="number" name="deductions" min="0" step="1" value="0"></label>
                <label></label>
            </div>
            <button class="btn sm" type="submit">ثبت کارکرد</button>
        </form>
    </div>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">💰 دوره‌های حقوق</h2>
    <table class="data">
        <tr><th>دوره</th><th>وضعیت</th><th>ناخالص</th><th>خالص</th><th>فیش‌ها</th><th></th></tr>
        @forelse($payrollRuns as $run)
            <tr>
                <td class="num">{{ $run->periodMonth() }}</td>
                <td>
                    @if($run->status() === 'paid')<span class="badge green">پرداخت‌شده</span>
                    @elseif($run->status() === 'processed')<span class="badge blue">پردازش‌شده</span>
                    @else<span class="badge amber">پیش‌نویس</span>@endif
                </td>
                <td class="num">{{ trans_number($run->totalGross()) }}</td>
                <td class="num">{{ trans_number($run->totalNet()) }}</td>
                <td class="num">{{ trans_number($run->slipCount()) }}</td>
                <td>
                    @if($run->status() === 'processed')
                        <form method="post" action="/admin/erp/hr/payroll/{{ $run->id() }}/pay" class="inline">
                            @csrf
                            <select name="method" style="width:auto;display:inline-block"><option value="bank">بانک</option><option value="cash">صندوق</option></select>
                            <button class="btn sm success" type="submit">پرداخت حقوق</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">دورهٔ حقوقی ثبت نشده است.</td></tr>
        @endforelse
    </table>
    <form method="post" action="/admin/erp/hr/payroll" style="margin-top:10px" onsubmit="return confirm('حقوق {{ '' }} پردازش شود؟ فیش‌ها و سند هزینهٔ حقوق صادر می‌شود.')">
        @csrf
        <input type="hidden" name="period_month" value="{{ $month }}">
        <button class="btn" type="submit">پردازش حقوق {{ $month }}</button>
    </form>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">➕ پرسنل جدید</h2>
    <form method="post" action="/admin/erp/hr/employees">
        @csrf
        <div class="form-grid">
            <label>کد پرسنلی<input type="text" name="employee_code" required maxlength="50"></label>
            <label>نام<input type="text" name="name" required maxlength="190"></label>
            <label>سمت<input type="text" name="position" maxlength="100"></label>
            <label>حقوق پایه (ریال)<input type="number" name="base_salary" min="0" step="1" required></label>
            <label>کد ملی<input type="text" name="national_id" maxlength="10" placeholder="رمزنگاری‌شده ذخیره می‌شود"></label>
            <label>تاریخ استخدام<input type="date" name="hire_date"></label>
        </div>
        <button class="btn sm" type="submit">ثبت پرسنل</button>
    </form>
</div>
@endsection

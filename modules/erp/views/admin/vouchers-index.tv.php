@extends('erp::layouts.admin')

@section('title', 'اسناد حسابداری')

@section('content')
<div class="topline">
    <h1>🧾 اسناد حسابداری</h1>
    <span class="spacer"></span>
    <form method="get" class="inline" style="display:flex;gap:6px">
        <select name="status" onchange="this.form.submit()">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach(['draft' => 'پیش‌نویس', 'approved' => 'تأییدشده', 'posted' => 'ثبت‌شده', 'voided' => 'باطل‌شده'] as $value => $label)
                <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </form>
    <a class="btn" href="/admin/erp/vouchers/create">سند جدید</a>
</div>

<div class="card">
    <table class="data">
        <tr><th>شماره</th><th>تاریخ</th><th>شرح</th><th>منبع</th><th>بدهکار</th><th>بستانکار</th><th>وضعیت</th><th></th></tr>
        @forelse($vouchers as $voucher)
            <tr>
                <td class="num">{{ $voucher->voucherNumber() }}</td>
                <td class="num">{{ $voucher->date() }}</td>
                <td>{{ $voucher->description() ?? '—' }}</td>
                <td><span class="badge">{{ $voucher->sourceModule() }}</span></td>
                <td class="num">{{ trans_number($voucher->totalDebit()) }}</td>
                <td class="num">{{ trans_number($voucher->totalCredit()) }}</td>
                <td>
                    @php $st = $voucher->status()->value; @endphp
                    <span class="badge {{ $st === 'posted' ? 'green' : ($st === 'draft' ? 'amber' : ($st === 'voided' ? 'red' : 'blue')) }}">{{ $voucher->status()->labelFa() }}</span>
                </td>
                <td><a class="btn sm ghost" href="/admin/erp/vouchers/{{ $voucher->id() }}">مشاهده</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">سندی ثبت نشده است.</td></tr>
        @endforelse
    </table>
    @include('erp::partials.pagination', ['baseUrl' => '/admin/erp/vouchers', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>
@endsection

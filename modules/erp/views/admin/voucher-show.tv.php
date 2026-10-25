@extends('erp::layouts.admin')

@section('title', 'سند ' . $voucher->voucherNumber())

@section('content')
<div class="topline">
    <h1>🧾 سند {{ $voucher->voucherNumber() }}</h1>
    <span class="spacer"></span>
    @php $st = $voucher->status()->value; @endphp
    <span class="badge {{ $st === 'posted' ? 'green' : ($st === 'draft' ? 'amber' : ($st === 'voided' ? 'red' : 'blue')) }}">{{ $voucher->status()->labelFa() }}</span>
    @if($voucher->status()->value === 'draft')
        <form method="post" action="/admin/erp/vouchers/{{ $voucher->id() }}/approve" class="inline">@csrf<button class="btn sm success" type="submit">تأیید</button></form>
    @endif
    @if($voucher->status()->value === 'draft' || $voucher->status()->value === 'approved')
        <form method="post" action="/admin/erp/vouchers/{{ $voucher->id() }}/post" class="inline">@csrf<button class="btn sm" type="submit">ثبت قطعی</button></form>
    @endif
    @if($voucher->status()->value === 'posted')
        <form method="post" action="/admin/erp/vouchers/{{ $voucher->id() }}/void" class="inline" onsubmit="return confirm('سند با سند معکوس ابطال شود؟')">@csrf<button class="btn sm danger" type="submit">ابطال (سند معکوس)</button></form>
    @endif
</div>

<div class="card">
    <table class="data">
        <tr><th>تاریخ</th><td class="num">{{ $voucher->date() }}</td></tr>
        <tr><th>شرح</th><td>{{ $voucher->description() ?? '—' }}</td></tr>
        <tr><th>منبع</th><td><span class="badge">{{ $voucher->sourceModule() }}</span> {{ $voucher->sourceId() ? '— ' . $voucher->sourceId() : '' }}</td></tr>
        @if($voucher->reverseOf() !== null)
            <tr><th>باطل‌شده توسط</th><td><a href="/admin/erp/vouchers/{{ $voucher->reverseOf() }}">سند معکوس</a></td></tr>
        @endif
        <tr><th>ثبت قطعی</th><td class="num">{{ $voucher->postedAt() !== null ? $voucher->postedAt()->format('Y-m-d H:i:s') : '—' }}</td></tr>
    </table>
</div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">ردیف‌های سند</h2>
    <table class="data">
        <tr><th>حساب</th><th>شرح</th><th>بدهکار</th><th>بستانکار</th></tr>
        @foreach($voucher->lines() as $line)
            <tr>
                <td>{{ $accountTitles[$line->accountId()] ?? ('#' . $line->accountId()) }}</td>
                <td>{{ $line->description() ?? '—' }}</td>
                <td class="num">{{ $line->debit() > 0 ? trans_number($line->debit()) : '—' }}</td>
                <td class="num">{{ $line->credit() > 0 ? trans_number($line->credit()) : '—' }}</td>
            </tr>
        @endforeach
        <tr>
            <th colspan="2">جمع</th>
            <th class="num">{{ trans_number($voucher->totalDebit()) }}</th>
            <th class="num">{{ trans_number($voucher->totalCredit()) }}</th>
        </tr>
    </table>
    @if($voucher->isBalanced())
        <p class="muted" style="margin-top:8px">✓ سند تراز است (SUM debit = SUM credit).</p>
    @else
        <p style="margin-top:8px;color:#b91c1c">✗ سند تراز نیست!</p>
    @endif
</div>
@endsection

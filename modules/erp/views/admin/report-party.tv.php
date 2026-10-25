@extends('erp::layouts.admin')

@section('title', 'حساب اشخاص')

@section('content')
<div class="topline"><h1>📈 حساب مشتری/تأمین‌کننده</h1></div>

<div class="card">
    <form method="get" style="display:flex;gap:8px;align-items:end">
        <label style="flex-direction:column;font-size:.85rem">نوع
            <select name="type">
                <option value="customer" {{ $partyType === 'customer' ? 'selected' : '' }}>مشتری</option>
                <option value="supplier" {{ $partyType === 'supplier' ? 'selected' : '' }}>تأمین‌کننده</option>
            </select>
        </label>
        <label style="flex:1;flex-direction:column;font-size:.85rem">شناسه
            <input type="text" name="id" value="{{ $partyId }}" placeholder="شناسهٔ مشتری/تأمین‌کننده (ULID)">
        </label>
        <button class="btn sm" type="submit">مشاهده</button>
    </form>
</div>

@if($statement !== null)
<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">
        {{ $partyType === 'supplier' ? $statement['supplier']->name() : $statement['customer']->name() }}
        — مانده: <span class="num">{{ trans_number($statement['balance']) }} ریال</span>
    </h2>
    <table class="data">
        <tr><th>تاریخ</th><th>مرجع</th><th>نوع</th><th>بدهکار</th><th>بستانکار</th></tr>
        @foreach($statement['entries'] as $entry)
            <tr>
                <td class="num">{{ $entry['date'] }}</td>
                <td class="num">{{ $entry['ref'] }}</td>
                <td><span class="badge">{{ $entry['type'] === 'invoice' ? 'فاکتور' : 'پرداخت' }}</span></td>
                <td class="num">{{ $entry['debit'] > 0 ? trans_number($entry['debit']) : '—' }}</td>
                <td class="num">{{ $entry['credit'] > 0 ? trans_number($entry['credit']) : '—' }}</td>
            </tr>
        @endforeach
    </table>
</div>
@endif
@endsection

@extends('erp::layouts.admin')

@section('title', 'سند جدید')

@section('content')
<div class="topline"><h1>🧾 سند حسابداری جدید</h1></div>

<div class="card">
    <form method="post" action="/admin/erp/vouchers" id="voucher-form">
        @csrf
        <div class="form-grid">
            <label>تاریخ سند<input type="date" name="date" required value="{{ date('Y-m-d') }}"></label>
            <label>شرح<input type="text" name="description" maxlength="500"></label>
        </div>

        <h2 style="font-size:.95rem;margin:12px 0 8px">ردیف‌ها</h2>
        <table class="data" id="lines">
            <tr><th>حساب</th><th style="width:150px">بدهکار (ریال)</th><th style="width:150px">بستانکار (ریال)</th></tr>
            @foreach([0, 1, 2, 3] as $i)
            <tr>
                <td>
                    <select name="account_code[]">
                        <option value="">— انتخاب حساب —</option>
                        @foreach($accounts as $account)
                            @if($account->isLeaf())
                                <option value="{{ $account->code() }}">{{ $account->code() }} — {{ $account->title() }}</option>
                            @endif
                        @endforeach
                    </select>
                </td>
                <td><input type="number" name="debit[]" min="0" step="1" value="0"></td>
                <td><input type="number" name="credit[]" min="0" step="1" value="0"></td>
            </tr>
            @endforeach
        </table>
        <p class="muted" style="margin:8px 0">🔒 قانون طلایی: جمع بدهکار باید دقیقاً برابر جمع بستانکار باشد (ردیف‌های خالی نادیده گرفته می‌شوند).</p>
        <button class="btn" type="submit">ثبت سند</button>
        <a class="btn ghost" href="/admin/erp/vouchers">انصراف</a>
    </form>
</div>
@endsection

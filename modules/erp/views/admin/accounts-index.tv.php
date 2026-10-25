@extends('erp::layouts.admin')

@section('title', 'سرفصل حساب‌ها')

@section('content')
<div class="topline">
    <h1>🌳 سرفصل حساب‌ها (Chart of Accounts)</h1>
    <span class="spacer"></span>
    <span class="muted">{{ trans_number(count($accounts)) }} حساب</span>
</div>

<div class="card">
    <table class="data">
        <tr><th>کد</th><th>عنوان</th><th>نوع</th><th>سطح</th><th>ماندهٔ طبیعی</th><th>تفصیلی</th></tr>
        @foreach($accounts as $account)
            <tr>
                <td class="num" style="padding-right:{{ 8 + ($account->level() - 1) * 18 }}px">{{ $account->code() }}</td>
                <td>{{ $account->title() }}</td>
                <td><span class="badge">{{ $account->type()->labelFa() }}</span></td>
                <td class="num">{{ trans_number($account->level()) }}</td>
                <td>{{ $account->normalBalance() === 'debit' ? 'بدهکار' : 'بستانکار' }}</td>
                <td>@if($account->isLeaf())<span class="badge green">بله</span>@else<span class="badge">گروه</span>@endif</td>
            </tr>
        @endforeach
    </table>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">➕ حساب جدید</h2>
    <form method="post" action="/admin/erp/accounts">
        @csrf
        <div class="form-grid">
            <label>کد حساب<input type="text" name="code" required maxlength="50" placeholder="مثلاً 1305"></label>
            <label>عنوان<input type="text" name="title" required maxlength="190"></label>
            <label>نوع
                <select name="type" required>
                    <option value="asset">دارایی</option>
                    <option value="liability">بدهی</option>
                    <option value="equity">حقوق صاحبان سهام</option>
                    <option value="revenue">درآمد</option>
                    <option value="expense">هزینه</option>
                </select>
            </label>
            <label>کد حساب والد (اختیاری)<input type="text" name="parent_code" maxlength="50"></label>
        </div>
        <button class="btn" type="submit">ایجاد حساب</button>
    </form>
</div>
@endsection

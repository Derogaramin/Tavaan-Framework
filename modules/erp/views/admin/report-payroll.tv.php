@extends('erp::layouts.admin')

@section('title', 'گزارش حقوق')

@section('content')
<div class="topline">
    <h1>📈 حقوق و دستمزد</h1>
    <span class="spacer"></span>
    <form method="get" class="inline" style="display:flex;gap:6px">
        <input type="text" name="month" value="{{ $month }}" pattern="\d{4}-(0[1-9]|1[0-2])" placeholder="YYYY-MM">
        <button class="btn sm ghost" type="submit">اعمال</button>
    </form>
</div>

<div class="card">
    <table class="data">
        <tr><th>کد پرسنلی</th><th>نام</th><th>پایه</th><th>اضافه‌کاری</th><th>کسورات</th><th>مالیات</th><th>خالص</th></tr>
        @php $totalNet = 0; @endphp
        @forelse($rows as $row)
            @php $totalNet += $row['net']; @endphp
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['employee'] }}</td>
                <td class="num">{{ trans_number($row['base']) }}</td>
                <td class="num">{{ trans_number($row['overtime']) }}</td>
                <td class="num">{{ trans_number($row['deductions']) }}</td>
                <td class="num">{{ trans_number($row['tax']) }}</td>
                <td class="num">{{ trans_number($row['net']) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">فیشی برای این دوره وجود ندارد.</td></tr>
        @endforelse
        @if(count($rows) > 0)
            <tr><th colspan="6">جمع خالص</th><th class="num">{{ trans_number($totalNet) }}</th></tr>
        @endif
    </table>
</div>
@endsection

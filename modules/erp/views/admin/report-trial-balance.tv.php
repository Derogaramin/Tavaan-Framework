@extends('erp::layouts.admin')

@section('title', 'تراز آزمایشی')

@section('content')
<div class="topline">
    <h1>📈 تراز آزمایشی</h1>
    <span class="spacer"></span>
    <form method="get" class="inline" style="display:flex;gap:6px">
        <input type="date" name="from" value="{{ $from ?? '' }}">
        <input type="date" name="to" value="{{ $to ?? '' }}">
        <button class="btn sm ghost" type="submit">اعمال</button>
    </form>
</div>

<div class="card">
    <table class="data">
        <tr><th>کد</th><th>عنوان</th><th>نوع</th><th>بدهکار</th><th>بستانکار</th><th>مانده</th></tr>
        @foreach($report['rows'] as $row)
            <tr>
                <td class="num">{{ $row['code'] }}</td>
                <td>{{ $row['title'] }}</td>
                <td class="muted">{{ $row['type'] }}</td>
                <td class="num">{{ trans_number($row['debit']) }}</td>
                <td class="num">{{ trans_number($row['credit']) }}</td>
                <td class="num {{ $row['balance'] < 0 ? 'neg' : 'pos' }}">{{ trans_number($row['balance']) }}</td>
            </tr>
        @endforeach
        <tr>
            <th colspan="3">جمع</th>
            <th class="num">{{ trans_number($report['total_debit']) }}</th>
            <th class="num">{{ trans_number($report['total_credit']) }}</th>
            <th>
                @if($report['balanced'])<span class="badge green">تراز ✓</span>@else<span class="badge red">ناتراز!</span>@endif
            </th>
        </tr>
    </table>
</div>
@endsection

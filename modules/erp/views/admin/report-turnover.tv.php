@extends('erp::layouts.admin')

@section('title', 'گردش کالا')

@section('content')
<div class="topline">
    <h1>📈 گردش کالا</h1>
    <span class="spacer"></span>
    <form method="get" class="inline" style="display:flex;gap:6px">
        <input type="date" name="from" value="{{ $from ?? '' }}">
        <input type="date" name="to" value="{{ $to ?? '' }}">
        <button class="btn sm ghost" type="submit">اعمال</button>
    </form>
</div>

<div class="card">
    <table class="data">
        <tr><th>SKU</th><th>نام</th><th>واحد</th><th>ورود</th><th>خروج</th><th>موجودی</th><th>میانگین بها</th><th>ارزش موجودی</th></tr>
        @foreach($rows as $row)
            <tr>
                <td>{{ $row['sku'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['unit'] }}</td>
                <td class="num pos">{{ trans_number($row['received']) }}</td>
                <td class="num neg">{{ trans_number($row['issued']) }}</td>
                <td class="num">{{ trans_number($row['on_hand']) }}</td>
                <td class="num">{{ trans_number($row['avg_cost']) }}</td>
                <td class="num">{{ trans_number($row['value']) }}</td>
            </tr>
        @endforeach
    </table>
</div>
@endsection

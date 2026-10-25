@extends('erp::layouts.admin')

@section('title', 'ترازنامه')

@section('content')
<div class="topline">
    <h1>📈 ترازنامه</h1>
    <span class="spacer"></span>
    <form method="get" class="inline" style="display:flex;gap:6px">
        <input type="date" name="to" value="{{ $to }}" placeholder="تا تاریخ">
        <button class="btn sm ghost" type="submit">اعمال</button>
    </form>
</div>

<div class="card">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
            <h2 style="font-size:.95rem;margin-bottom:8px">دارایی‌ها</h2>
            <table class="data">
                @foreach($report['asset_rows'] as $row)
                    <tr><td>{{ $row['code'] }} — {{ $row['title'] }}</td><td class="num">{{ trans_number($row['balance']) }}</td></tr>
                @endforeach
                <tr><th>جمع دارایی‌ها</th><th class="num">{{ trans_number($report['assets']) }}</th></tr>
            </table>
        </div>
        <div>
            <h2 style="font-size:.95rem;margin-bottom:8px">بدهی‌ها و حقوق صاحبان سهام</h2>
            <table class="data">
                @foreach($report['liability_rows'] as $row)
                    <tr><td>{{ $row['code'] }} — {{ $row['title'] }}</td><td class="num">{{ trans_number($row['balance']) }}</td></tr>
                @endforeach
                @foreach($report['equity_rows'] as $row)
                    <tr><td>{{ $row['code'] }} — {{ $row['title'] }}</td><td class="num">{{ trans_number($row['balance']) }}</td></tr>
                @endforeach
                <tr><td>سود (زیان) دوره</td><td class="num {{ $report['net_income'] >= 0 ? 'pos' : 'neg' }}">{{ trans_number($report['net_income']) }}</td></tr>
                <tr><th>جمع بدهی + حقوق + سود</th><th class="num">{{ trans_number($report['liabilities'] + $report['equity'] + $report['net_income']) }}</th></tr>
            </table>
        </div>
    </div>
    <p style="margin-top:12px">
        @if($report['balanced'])
            <span class="badge green">⚖ متوازن: A = L + E + NI ✓ (E2E-05)</span>
        @else
            <span class="badge red">✗ ترازنامه متوازن نیست — بررسی اسناد</span>
        @endif
    </p>
</div>
@endsection

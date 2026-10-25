@extends('erp::layouts.admin')

@section('title', 'صورت سود و زیان')

@section('content')
<div class="topline">
    <h1>📈 صورت سود و زیان</h1>
    <span class="spacer"></span>
    <form method="get" class="inline" style="display:flex;gap:6px">
        <input type="date" name="from" value="{{ $from ?? '' }}">
        <input type="date" name="to" value="{{ $to ?? '' }}">
        <button class="btn sm ghost" type="submit">اعمال</button>
    </form>
</div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">درآمدها</h2>
    <table class="data">
        @forelse($report['revenue_rows'] as $row)
            <tr><td>{{ $row['code'] }} — {{ $row['title'] }}</td><td class="num pos">{{ trans_number($row['balance']) }}</td></tr>
        @empty
            <tr><td class="muted">—</td><td></td></tr>
        @endforelse
        <tr><th>جمع درآمد</th><th class="num">{{ trans_number($report['revenue']) }}</th></tr>
    </table>

    <h2 style="font-size:.95rem;margin:14px 0 8px">هزینه‌ها</h2>
    <table class="data">
        @forelse($report['expense_rows'] as $row)
            <tr><td>{{ $row['code'] }} — {{ $row['title'] }}</td><td class="num neg">{{ trans_number($row['balance']) }}</td></tr>
        @empty
            <tr><td class="muted">—</td><td></td></tr>
        @endforelse
        <tr><th>جمع هزینه</th><th class="num">{{ trans_number($report['expense']) }}</th></tr>
    </table>

    <h2 style="font-size:1rem;margin-top:14px">
        سود (زیان) خالص:
        <span class="num {{ $report['net_income'] >= 0 ? 'pos' : 'neg' }}">{{ trans_number($report['net_income']) }} ریال</span>
    </h2>
</div>
@endsection

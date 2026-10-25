@extends('erp::layouts.admin')

@section('title', 'بهای تمام‌شده')

@section('content')
<div class="topline"><h1>📈 بهای تمام‌شدهٔ تولید</h1></div>

<div class="card">
    <table class="data">
        <tr><th>شماره WO</th><th>محصول</th><th>برنامه</th><th>تولیدشده</th><th>بهای مواد</th><th>بهای هر واحد</th><th>وضعیت</th></tr>
        @forelse($rows as $row)
            <tr>
                <td class="num">{{ $row['wo_number'] }}</td>
                <td>{{ $row['product'] }}</td>
                <td class="num">{{ trans_number($row['planned']) }}</td>
                <td class="num">{{ trans_number($row['produced']) }}</td>
                <td class="num">{{ trans_number($row['material_cost']) }}</td>
                <td class="num">{{ trans_number($row['unit_cost']) }}</td>
                <td><span class="badge {{ $row['status'] === 'completed' ? 'green' : 'amber' }}">{{ $row['status'] }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">سفارش تولیدی وجود ندارد.</td></tr>
        @endforelse
    </table>
</div>
@endsection

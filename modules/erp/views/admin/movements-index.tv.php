@extends('erp::layouts.admin')

@section('title', 'گردش موجودی')

@section('content')
<div class="topline">
    <h1>🔁 حرکات موجودی (append-only)</h1>
    <span class="spacer"></span>
    <form method="get" class="inline" style="display:flex;gap:6px">
        <select name="warehouse_id" onchange="this.form.submit()">
            <option value="">همهٔ انبارها</option>
            @foreach($warehouses as $warehouse)
                <option value="{{ $warehouse->id() }}" {{ ($filters['warehouse_id'] ?? '') === $warehouse->id() ? 'selected' : '' }}>{{ $warehouse->name() }}</option>
            @endforeach
        </select>
        <select name="type" onchange="this.form.submit()">
            <option value="">همهٔ انواع</option>
            @foreach(['receipt' => 'رسید', 'issue' => 'حواله', 'transfer_in' => 'انتقال ورود', 'transfer_out' => 'انتقال خروج'] as $value => $label)
                <option value="{{ $value }}" {{ ($filters['type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="card">
    <table class="data">
        <tr><th>زمان</th><th>نوع</th><th>کالا</th><th>مقدار</th><th>بهای واحد</th><th>ارزش</th><th>مرجع</th></tr>
        @forelse($movements as $movement)
            <tr>
                <td class="num">{{ $movement->createdAt()->format('Y-m-d H:i') }}</td>
                <td><span class="badge {{ $movement->type()->increases() ? 'green' : 'amber' }}">{{ $movement->type()->labelFa() }}</span></td>
                <td>{{ $itemNames[$movement->itemId()] ?? $movement->itemId() }}</td>
                <td class="num {{ $movement->type()->increases() ? 'pos' : 'neg' }}">{{ $movement->type()->increases() ? '+' : '−' }}{{ trans_number($movement->quantity()) }}</td>
                <td class="num">{{ trans_number($movement->unitCost()) }}</td>
                <td class="num">{{ trans_number($movement->totalValue()) }}</td>
                <td class="muted">{{ $movement->referenceType() ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">حرکتی ثبت نشده است.</td></tr>
        @endforelse
    </table>
    @include('erp::partials.pagination', ['baseUrl' => '/admin/erp/inventory/movements', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>
@endsection

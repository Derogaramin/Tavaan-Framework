@extends('crm::layouts.admin')

@section('title', 'فرصت‌های فروش')

@section('content')
<div class="topline">
    <h1>📈 فرصت‌های فروش</h1>
    <span class="spacer"></span>
    <a class="btn" href="/admin/crm/opportunities/create">+ فرصت جدید</a>
</div>

@if($forecast !== null)
<div class="stats">
    <div class="stat"><div class="num">{{ trans_number($forecast['count']) }}</div><div class="muted">فرصت باز</div></div>
    <div class="stat"><div class="num">{{ trans_number($forecast['total_value']) }}</div><div class="muted">ارزش کل (ریال)</div></div>
    <div class="stat"><div class="num">{{ trans_number($forecast['weighted']) }}</div><div class="muted">پیش‌بینی وزنی (ریال)</div></div>
</div>
@endif

<div class="card">
    <form method="get" action="/admin/crm/opportunities" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <label style="flex:1;min-width:180px;font-size:.85rem">{{ trans('crm::messages.search') }}
            <input type="text" name="q" value="{{ $query }}">
        </label>
        <label style="font-size:.85rem">{{ trans('crm::messages.stage') }}
            <select name="stage">
                <option value="">همه</option>
                @foreach(\Modules\Crm\Domain\Enums\OpportunityStage::cases() as $stage)
                    <option value="{{ $stage->value }}" {{ $stageFilter === $stage->value ? 'selected' : '' }}>{{ $stage->labelFa() }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn ghost" type="submit">فیلتر</button>
    </form>
</div>

<div class="card">
    <table class="data">
        <thead><tr><th>عنوان</th><th>مخاطب</th><th>ارزش</th><th>مرحله</th><th>احتمال</th><th>وزنی</th><th>بستن انتظار</th></tr></thead>
        <tbody>
        @forelse($opportunities as $opp)
            <tr>
                <td><a href="/admin/crm/opportunities/{{ $opp->id() }}"><strong>{{ $opp->title() }}</strong></a></td>
                <td>{{ $contacts[$opp->contactId()] ?? '—' }}</td>
                <td>{{ trans_number($opp->valueMinor()) }}</td>
                <td>
                    @if($opp->stage()->isWon())<span class="badge green">{{ $opp->stage()->labelFa() }}</span>
                    @elseif($opp->stage() === \Modules\Crm\Domain\Enums\OpportunityStage::ClosedLost)<span class="badge red">{{ $opp->stage()->labelFa() }}</span>
                    @else<span class="badge blue">{{ $opp->stage()->labelFa() }}</span>@endif
                </td>
                <td>{{ trans_number($opp->probability()) }}٪</td>
                <td>{{ trans_number($opp->weightedValue()) }}</td>
                <td class="muted">{{ $opp->expectedCloseDate() ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">{{ trans('crm::messages.no_results') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    @include('crm::partials.pagination', ['baseUrl' => '/admin/crm/opportunities', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>
@endsection

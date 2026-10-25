@extends('crm::layouts.admin')

@section('title', 'سرنخ‌ها')

@section('content')
<div class="topline">
    <h1>🎯 سرنخ‌ها</h1>
    <span class="spacer"></span>
    <a class="btn" href="/admin/crm/leads/create">+ سرنخ جدید</a>
</div>

<div class="card">
    <form method="get" action="/admin/crm/leads" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <label style="flex:1;min-width:180px;font-size:.85rem">{{ trans('crm::messages.search') }}
            <input type="text" name="q" value="{{ $query }}">
        </label>
        <label style="font-size:.85rem">{{ trans('crm::messages.status') }}
            <select name="status">
                <option value="">همه</option>
                @foreach(\Modules\Crm\Domain\Enums\LeadStatus::cases() as $s)
                    <option value="{{ $s->value }}" {{ $statusFilter === $s->value ? 'selected' : '' }}>{{ $s->labelFa() }}</option>
                @endforeach
            </select>
        </label>
        <label style="font-size:.85rem">حداقل امتیاز
            <input type="number" name="min_score" min="0" max="100" style="max-width:90px">
        </label>
        <button class="btn ghost" type="submit">فیلتر</button>
    </form>
</div>

<div class="card">
    <table class="data">
        <thead><tr><th>عنوان</th><th>مخاطب</th><th>ارزش</th><th>امتیاز</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        @forelse($leads as $lead)
            <tr>
                <td><a href="/admin/crm/leads/{{ $lead->id() }}"><strong>{{ $lead->title() }}</strong></a></td>
                <td>{{ $contacts[$lead->contactId()] ?? '—' }}</td>
                <td>{{ trans_number($lead->valueMinor()) }} ریال</td>
                <td>
                    <span class="score-pill {{ $lead->score() >= 70 ? 'score-hot' : ($lead->score() >= 40 ? 'score-warm' : 'score-cold') }}">
                        {{ trans_number($lead->score()) }}
                    </span>
                </td>
                <td>
                    @if($lead->status() === \Modules\Crm\Domain\Enums\LeadStatus::New)<span class="badge blue">جدید</span>
                    @elseif($lead->status() === \Modules\Crm\Domain\Enums\LeadStatus::Qualified)<span class="badge amber">واجد صلاحیت</span>
                    @elseif($lead->status() === \Modules\Crm\Domain\Enums\LeadStatus::Converted)<span class="badge green">تبدیل‌شده</span>
                    @else<span class="badge red">ازدست‌رفته</span>@endif
                </td>
                <td><a class="btn sm ghost" href="/admin/crm/leads/{{ $lead->id() }}">جزئیات</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">{{ trans('crm::messages.no_results') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    @include('crm::partials.pagination', ['baseUrl' => '/admin/crm/leads', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>
@endsection

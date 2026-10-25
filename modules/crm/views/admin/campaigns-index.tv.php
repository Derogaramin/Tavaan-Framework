@extends('crm::layouts.admin')

@section('title', 'کمپین‌ها')

@section('content')
<div class="topline">
    <h1>📣 کمپین‌ها</h1>
    <span class="spacer"></span>
    <a class="btn" href="/admin/crm/campaigns/create">+ کمپین جدید</a>
</div>

<div class="card">
    <table class="data">
        <thead><tr><th>نام</th><th>نوع</th><th>وضعیت</th><th>ارسال‌شده</th><th>ناموفق</th><th>زمان‌بندی</th><th>عملیات</th></tr></thead>
        <tbody>
        @forelse($campaigns as $campaign)
            <tr>
                <td><a href="/admin/crm/campaigns/{{ $campaign->id() }}/edit"><strong>{{ $campaign->name() }}</strong></a></td>
                <td>{{ $campaign->type() === 'email' ? '✉️ ایمیل' : '📱 پیامک' }}</td>
                <td>
                    @if($campaign->status() === \Modules\Crm\Domain\Enums\CampaignStatus::Sent)<span class="badge green">ارسال‌شده</span>
                    @elseif($campaign->status() === \Modules\Crm\Domain\Enums\CampaignStatus::Failed)<span class="badge red">ناموفق</span>
                    @elseif($campaign->status() === \Modules\Crm\Domain\Enums\CampaignStatus::Scheduled)<span class="badge amber">زمان‌بندی‌شده</span>
                    @elseif($campaign->status() === \Modules\Crm\Domain\Enums\CampaignStatus::Sending)<span class="badge blue">در حال ارسال</span>
                    @else<span class="badge">پیش‌نویس</span>@endif
                </td>
                <td>{{ trans_number($campaign->sentCount()) }}</td>
                <td>{{ trans_number($campaign->failedCount()) }}</td>
                <td class="muted">{{ $campaign->scheduledAt() !== null ? jalali($campaign->scheduledAt(), 'Y/m/d H:i') : '—' }}</td>
                <td><a class="btn sm ghost" href="/admin/crm/campaigns/{{ $campaign->id() }}/edit">ویرایش</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">{{ trans('crm::messages.no_results') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    @include('crm::partials.pagination', ['baseUrl' => '/admin/crm/campaigns', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>
@endsection

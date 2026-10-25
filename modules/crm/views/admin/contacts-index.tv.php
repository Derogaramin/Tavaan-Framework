@extends('crm::layouts.admin')

@section('title', 'مخاطبان')

@section('content')
<div class="topline">
    <h1>👥 مخاطبان</h1>
    <span class="spacer"></span>
    <span class="muted">{{ trans_number($total) }} مخاطب</span>
    <a class="btn" href="/admin/crm/contacts/create">+ مخاطب جدید</a>
</div>

<div class="card">
    <form method="get" action="/admin/crm/contacts" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <label style="flex:1;min-width:200px;font-size:.85rem">{{ trans('crm::messages.search') }}
            <input type="text" name="q" value="{{ $query }}" placeholder="{{ trans('crm::messages.search_placeholder') }}">
        </label>
        <label style="font-size:.85rem">{{ trans('crm::messages.status') }}
            <select name="status">
                <option value="">همه</option>
                @foreach(\Modules\Crm\Domain\Enums\ContactStatus::cases() as $s)
                    <option value="{{ $s->value }}">{{ $s->labelFa() }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn ghost" type="submit">فیلتر</button>
    </form>
</div>

<div class="card">
    <table class="data">
        <thead><tr><th>نام</th><th>شرکت</th><th>ایمیل</th><th>موبایل</th><th>منبع</th><th>مسئول</th><th>عملیات</th></tr></thead>
        <tbody>
        @forelse($contacts as $contact)
            <tr>
                <td><a href="/admin/crm/contacts/{{ $contact->id() }}"><strong>{{ $contact->fullName() }}</strong></a></td>
                <td>{{ $contact->company() ?? '—' }}</td>
                <td dir="ltr">{{ $contact->email() ?? '—' }}</td>
                <td dir="ltr">{{ $contact->mobile() ?? '—' }}</td>
                <td><span class="badge blue">{{ $contact->source() }}</span></td>
                <td>{{ $contact->ownerId() !== null ? ($owners[(string) $contact->ownerId()] ?? '—') : '—' }}</td>
                <td><a class="btn sm ghost" href="/admin/crm/contacts/{{ $contact->id() }}">پرونده</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">{{ trans('crm::messages.no_results') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    @include('crm::partials.pagination', ['baseUrl' => '/admin/crm/contacts', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>
@endsection

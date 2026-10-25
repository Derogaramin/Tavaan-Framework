@extends('crm::layouts.admin')

@php $preselectContact = (string) ($preselectContact ?? ''); @endphp

@section('title', 'سرنخ جدید')

@section('content')
<div class="topline">
    <h1>🎯 سرنخ جدید</h1>
    <span class="spacer"></span>
    <a class="btn ghost" href="/admin/crm/leads">← فهرست</a>
</div>

<div class="card">
    <form method="post" action="/admin/crm/leads">
        @csrf
        <div class="form-grid">
            <label>مخاطب *
                <select name="contact_id" required>
                    <option value="">— انتخاب —</option>
                    @foreach($contacts as $c)
                        <option value="{{ $c->id() }}" {{ $preselectContact === $c->id() ? 'selected' : '' }}>
                            {{ $c->fullName() }}{{ $c->company() !== null ? ' — ' . $c->company() : '' }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>{{ trans('crm::messages.lead_title') }} *
                <input type="text" name="title" required maxlength="255">
            </label>
            <label>{{ trans('crm::messages.lead_value') }}
                <input type="number" name="value" min="0" value="0">
            </label>
        </div>
        <p style="margin-top:12px">
            <button class="btn" type="submit">ثبت سرنخ</button>
            <span class="muted">— امتیاز به‌طور خودکار محاسبه می‌شود (Lead Scoring)</span>
        </p>
    </form>
</div>
@endsection

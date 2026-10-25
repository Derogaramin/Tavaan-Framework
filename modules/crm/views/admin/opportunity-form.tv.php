@extends('crm::layouts.admin')

@section('title', 'فرصت جدید')

@section('content')
<div class="topline">
    <h1>📈 فرصت جدید</h1>
    <span class="spacer"></span>
    <a class="btn ghost" href="/admin/crm/opportunities">← فهرست</a>
</div>

<div class="card">
    <form method="post" action="/admin/crm/opportunities">
        @csrf
        <div class="form-grid">
            <label>مخاطب *
                <select name="contact_id" required>
                    <option value="">— انتخاب —</option>
                    @foreach($contacts as $c)
                        <option value="{{ $c->id() }}">{{ $c->fullName() }}{{ $c->company() !== null ? ' — ' . $c->company() : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ trans('crm::messages.opportunity_title') }} *
                <input type="text" name="title" required maxlength="255">
            </label>
            <label>{{ trans('crm::messages.opportunity_value') }}
                <input type="number" name="value" min="0" value="0">
            </label>
            <label>{{ trans('crm::messages.expected_close_date') }}
                <input type="date" name="expected_close_date">
            </label>
        </div>
        <p style="margin-top:12px"><button class="btn" type="submit">ساخت فرصت</button>
            <span class="muted">— مرحلهٔ اولیه: احتمال اولیه (۱۰٪) + شروع Workflow pipeline</span></p>
    </form>
</div>
@endsection

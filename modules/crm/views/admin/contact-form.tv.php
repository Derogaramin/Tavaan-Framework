@extends('crm::layouts.admin')

@php $isEdit = $contact !== null; @endphp

@section('title', $isEdit ? 'ویرایش مخاطب' : 'مخاطب جدید')

@section('content')
<div class="topline">
    <h1>{{ $isEdit ? '✏️ ' . $contact->fullName() : '👤 مخاطب جدید' }}</h1>
    <span class="spacer"></span>
    <a class="btn ghost" href="/admin/crm/contacts">← فهرست</a>
</div>

<div class="card">
    <form method="post" action="{{ $isEdit ? '/admin/crm/contacts/' . $contact->id() : '/admin/crm/contacts' }}">
        @csrf
        <div class="form-grid">
            <label>{{ trans('crm::messages.first_name') }} *
                <input type="text" name="first_name" value="{{ $isEdit ? $contact->firstName() : '' }}" required>
            </label>
            <label>{{ trans('crm::messages.last_name') }}
                <input type="text" name="last_name" value="{{ $isEdit ? $contact->lastName() : '' }}">
            </label>
            <label>{{ trans('crm::messages.company') }}
                <input type="text" name="company" value="{{ $isEdit ? ($contact->company() ?? '') : '' }}">
            </label>
            <label>{{ trans('crm::messages.job_title') }}
                <input type="text" name="title" value="{{ $isEdit ? ($contact->title() ?? '') : '' }}">
            </label>
            <label>{{ trans('crm::messages.email') }}
                <input type="email" name="email" value="{{ $isEdit ? ($contact->email() ?? '') : '' }}" dir="ltr">
            </label>
            <label>{{ trans('crm::messages.mobile') }}
                <input type="text" name="mobile" value="{{ $isEdit ? ($contact->mobile() ?? '') : '' }}" dir="ltr">
            </label>
            <label>{{ trans('crm::messages.phone') }}
                <input type="text" name="phone" value="{{ $isEdit ? ($contact->phone() ?? '') : '' }}" dir="ltr">
            </label>
            <label>{{ trans('crm::messages.national_id') }}
                <input type="text" name="national_id" placeholder="{{ $isEdit && $contact->nationalId() !== null ? '••••' . mb_substr($contact->nationalId(), -4) : '' }}" dir="ltr">
                <span class="muted">{{ trans('crm::messages.national_id_hint') }}</span>
            </label>
            <label>{{ trans('crm::messages.source') }}
                <select name="source">
                    @foreach(['website' => 'وب‌سایت', 'referral' => 'معرفی', 'campaign' => 'کمپین', 'import' => 'ورود دسته‌جمعی', 'shop' => 'فروشگاه', 'support' => 'پشتیبانی'] as $key => $label)
                        <option value="{{ $key }}" {{ $isEdit && $contact->source() === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ trans('crm::messages.tags') }}
                <input type="text" name="tags" value="{{ $isEdit ? implode('، ', $contact->tags()) : '' }}">
            </label>
        </div>
        <p style="margin-top:12px"><button class="btn" type="submit">{{ trans('crm::messages.save') }}</button></p>
    </form>
</div>
@endsection

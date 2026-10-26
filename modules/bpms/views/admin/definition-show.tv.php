@extends('bpms::layouts.admin')

@section('title', $definition->name())

@section('content')
<div class="topline">
    <h1>🧩 {{ $definition->name() }} <span class="muted">v{{ trans_number($definition->version()) }}</span></h1>
    <span class="badge {{ $definition->status()->value === 'published' ? 'green' : ($definition->status()->value === 'draft' ? 'amber' : 'gray') }}">{{ trans('bpms::messages.' . $definition->status()->value) }}</span>
    @if($definition->isActive())<span class="badge blue">{{ trans('bpms::messages.active') }}</span>@endif
    <div class="spacer"></div>
    @if($canManage && $definition->status()->isEditable())
        <a class="btn sm" href="/admin/bpms/definitions/{{ $definition->id() }}/edit">ویرایش</a>
    @endif
    @if($canPublish && $definition->status()->value === 'draft')
        <form class="inline" method="post" action="/admin/bpms/definitions/{{ $definition->id() }}/publish">
            @csrf
            <button class="btn sm success" type="submit">{{ trans('bpms::messages.publish') }}</button>
        </form>
    @endif
    @if($canPublish && $definition->status()->value === 'published' && !$definition->isActive())
        <form class="inline" method="post" action="/admin/bpms/definitions/{{ $definition->id() }}/activate">
            @csrf
            <button class="btn sm" type="submit">{{ trans('bpms::messages.activate') }}</button>
        </form>
    @endif
    @if($canPublish && $definition->status()->value === 'published' && $definition->isActive())
        <form class="inline" method="post" action="/admin/bpms/definitions/{{ $definition->id() }}/retire"
              onsubmit="return confirm('نسخهٔ فعال بازنشسته شود؟ نمونه‌های جاری ادامه می‌یابند.')">
            @csrf
            <button class="btn sm danger" type="submit">{{ trans('bpms::messages.retire') }}</button>
        </form>
    @endif
</div>

<div class="card">
    <dl class="kv">
        <dt>کلید</dt><dd><code>{{ $definition->key() }}</code></dd>
        @if($definition->description() !== null)
            <dt>توضیحات</dt><dd>{{ $definition->description() }}</dd>
        @endif
        <dt>فرم‌ها</dt>
        <dd>
            @foreach($definition->formSchemas() as $formId => $schema)
                <span class="badge blue">{{ $formId }} ({{ trans_number(count($schema->fields)) }} فیلد)</span>
            @endforeach
            @if(count($definition->formSchemas()) === 0)<span class="muted">—</span>@endif
        </dd>
    </dl>
</div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">🗺 {{ trans('bpms::messages.diagram') }}</h2>
    <div class="diagram-wrap">{!! $svg !!}</div>
    <details style="margin-top:8px">
        <summary class="muted" style="cursor:pointer">{{ trans('bpms::messages.mermaid_source') }}</summary>
        <pre class="help" style="direction:ltr;text-align:left;overflow-x:auto;padding:8px;background:#f8fafc;border-radius:8px;margin-top:6px">{{ $mermaid }}</pre>
    </details>
</div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">🗂 نسخه‌های «{{ $definition->key() }}»</h2>
    <table class="data">
        <thead><tr><th>{{ trans('bpms::messages.version') }}</th><th>{{ trans('bpms::messages.status') }}</th><th>فعال</th><th></th></tr></thead>
        <tbody>
        @foreach($versions as $version)
            <tr>
                <td>{{ trans_number($version->version()) }}</td>
                <td><span class="badge {{ $version->status()->value === 'published' ? 'green' : ($version->status()->value === 'draft' ? 'amber' : 'gray') }}">{{ trans('bpms::messages.' . $version->status()->value) }}</span></td>
                <td>{{ $version->isActive() ? '✅' : '—' }}</td>
                <td><a class="btn sm ghost" href="/admin/bpms/definitions/{{ $version->id() }}">مشاهده</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection

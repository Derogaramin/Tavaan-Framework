@extends('admin::layout')

@section('title', 'نمایش ' . $resource->titleText())

@section('content')
    <h1 class="admin-title">{{ $resource->iconGlyph() }} نمایش {{ $resource->titleText() }} #{{ $model->getKey() }}</h1>

    <div class="admin-card">
        <table class="admin-table">
            @foreach($fields as $fieldName)
                <tr>
                    <th style="width:220px">{{ $fieldName }}</th>
                    <td>
                        @php
                            $showField = $form->field($fieldName);
                            $rawValue = $model->{$fieldName} ?? null;
                            $displayValue = $showField !== null
                                ? $transformer->toDisplay($showField->type, $rawValue, $showField->jalaliFormatValue())
                                : (is_bool($rawValue) ? $transformer->toDisplay('static', $rawValue) : $rawValue);
                        @endphp
                        @if($showField !== null && $showField->type === 'richtext')
                            {!! is_string($displayValue) ? $displayValue : '' !!}
                        @else
                            {{ is_scalar($displayValue) || $displayValue === null ? $displayValue : json_encode($displayValue, JSON_UNESCAPED_UNICODE) }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>

        <div class="toolbar" style="margin-top:16px">
            @if($can['update'] ?? false)
                <a class="btn primary" href="/{{ $adminPrefix }}/{{ $resource->key() }}/{{ $model->getKey() }}/edit">ویرایش</a>
            @endif
            @foreach($actions as $action)
                <form method="post" style="display:inline"
                      action="/{{ $adminPrefix }}/{{ $resource->key() }}/{{ $model->getKey() }}/actions/{{ $action->name }}">
                    <input type="hidden" name="_token" value="{{ $token }}">
                    <button class="btn" type="submit">{{ $action->labelText() }}</button>
                </form>
            @endforeach
            @if($can['delete'] ?? false)
                <form method="post" style="display:inline"
                      action="/{{ $adminPrefix }}/{{ $resource->key() }}/{{ $model->getKey() }}"
                      onsubmit="return confirm('حذف شود؟')">
                    <input type="hidden" name="_token" value="{{ $token }}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="btn danger" type="submit">حذف</button>
                </form>
            @endif
            <a class="btn" href="/{{ $adminPrefix }}/{{ $resource->key() }}">بازگشت به فهرست</a>
        </div>
    </div>

    @foreach($panels as $panel)
        <div class="admin-card">
            <h3 style="margin-top:0">🔗 {{ $panel['name'] }}</h3>
            <table class="admin-table">
                <thead>
                <tr>
                    @foreach($panel['columns'] as $panelColumn)
                        <th>{{ $panelColumn }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($panel['rows'] as $panelRow)
                    <tr>
                        @foreach($panel['columns'] as $panelColumn)
                            <td>{{ $panelRow[$panelColumn] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                @if(count($panel['rows']) === 0)
                    <tr>
                        <td colspan="{{ count($panel['columns']) }}" class="muted">خالی</td>
                    </tr>
                @endif
                </tbody>
            </table>
        </div>
    @endforeach
@endsection

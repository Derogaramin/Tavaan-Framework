@extends('poll::admin.layout')

@section('content')
@php
    $isEdit = isset($poll) && $poll !== null;
    $old = is_array($old ?? null) ? $old : [];
    $votes = (int) ($voteCount ?? 0);
    $locked = $isEdit && $votes > 0;

    $value = static function (string $field, string $fallback = '') use ($isEdit, $poll, $old): string {
        if (isset($old[$field]) && $old[$field] !== null) {
            return (string) $old[$field];
        }
        return $isEdit ? $fallback : '';
    };

    $optionValues = [];
    if (isset($old['options']) && is_array($old['options'])) {
        foreach ($old['options'] as $o) {
            $optionValues[] = (string) $o;
        }
    } elseif ($isEdit) {
        $optionValues = $poll->options();
    }

    $multiple = isset($old['multiple'])
        ? (bool) $old['multiple']
        : ($isEdit ? $poll->multiple() : false);
@endphp

@if(is_string($ok ?? null) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif

@if(!empty($errors))
    <div class="errors">
        @foreach($errors as $field => $messages)
            @foreach((array) $messages as $message)
                <div>• {{ $message }}</div>
            @endforeach
        @endforeach
    </div>
@endif

<div class="card">
    <h2>{{ $isEdit ? trans('poll::messages.edit_poll') : trans('poll::messages.new_poll') }}</h2>

    <form method="post" action="{{ $isEdit ? '/admin/polls/' . $poll->id() : '/admin/polls' }}">
        @csrf

        <div class="field">
            <label for="question">{{ trans('poll::messages.field_question') }} *</label>
            <input type="text" id="question" name="question" required maxlength="500"
                   value="{{ $value('question', $isEdit ? $poll->question() : '') }}">
        </div>

        <div class="field">
            <label for="slug">{{ trans('poll::messages.field_slug') }}</label>
            <input type="text" id="slug" name="slug" maxlength="200" dir="auto"
                   value="{{ $value('slug', $isEdit ? $poll->slug() : '') }}"
                   placeholder="{{ trans('poll::messages.slug_auto_hint') }}">
        </div>

        <div class="field">
            <label>{{ trans('poll::messages.field_options') }} *</label>
            @if($locked)
                <p class="muted">🔒 {{ trans('poll::messages.options_locked_hint') }} ({{ trans('poll::messages.votes_count', ['count' => trans_number($votes)]) }})</p>
            @endif
            @for($i = 0; $i < $maxOptions; $i++)
                <input class="option-input" type="text" name="options[]" maxlength="255" dir="auto"
                       value="{{ $optionValues[$i] ?? '' }}"
                       placeholder="{{ trans('poll::messages.option_n', ['n' => trans_number($i + 1)]) }}"
                       {{ $locked ? 'readonly' : '' }}>
            @endfor
        </div>

        <div class="field checkline">
            <input type="hidden" name="multiple" value="0">
            <input type="checkbox" id="multiple" name="multiple" value="1" style="width:auto" {{ $multiple ? 'checked' : '' }}>
            <label for="multiple">{{ trans('poll::messages.field_multiple') }}</label>
        </div>

        <div class="grid2">
            <div class="field">
                <label for="starts_at">{{ trans('poll::messages.field_starts_at') }}</label>
                <input type="datetime-local" id="starts_at" name="starts_at"
                       value="{{ $value('starts_at', $isEdit && $poll->startsAt() !== null ? $poll->startsAt()->format('Y-m-d\TH:i') : '') }}">
            </div>
            <div class="field">
                <label for="ends_at">{{ trans('poll::messages.field_ends_at') }}</label>
                <input type="datetime-local" id="ends_at" name="ends_at"
                       value="{{ $value('ends_at', $isEdit && $poll->endsAt() !== null ? $poll->endsAt()->format('Y-m-d\TH:i') : '') }}">
                <p class="muted">{{ trans('poll::messages.window_hint') }}</p>
            </div>
        </div>

        @if(!$isEdit)
        <div class="field">
            <label for="status">{{ trans('poll::messages.field_status') }}</label>
            <select id="status" name="status">
                <option value="draft">{{ trans('poll::status.draft') }}</option>
                <option value="open">{{ trans('poll::status.open') }}</option>
            </select>
        </div>
        @endif

        <button class="btn" type="submit">{{ $isEdit ? trans('poll::messages.save') : trans('poll::messages.create') }}</button>
        <a class="btn gray" href="/admin/polls">{{ trans('poll::messages.back_to_list') }}</a>
    </form>

    @if($isEdit && $poll->status()->value !== 'open')
    <form method="post" action="/admin/polls/{{ $poll->id() }}/open" style="margin-block-start:1rem">
        @csrf
        <button class="btn green" type="submit">{{ trans('poll::messages.open_action') }}</button>
    </form>
    @endif

    @if($isEdit && $poll->status()->value === 'open')
    <form method="post" action="/admin/polls/{{ $poll->id() }}/close" style="margin-block-start:1rem">
        @csrf
        <button class="btn" type="submit">{{ trans('poll::messages.close_action') }}</button>
    </form>
    @endif

    @if($isEdit)
    <form method="post" action="/admin/polls/{{ $poll->id() }}" style="margin-block-start:1rem">
        @csrf
        @method('DELETE')
        <button class="btn red" type="submit" onclick="return confirm('{{ trans('poll::messages.confirm_delete') }}')">{{ trans('poll::messages.delete') }}</button>
    </form>
    @endif
</div>
@endsection

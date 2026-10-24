@extends('forum::admin.layout')

@section('content')
<div class="card" style="max-width:720px">
    <h2>{{ $forum === null ? trans('forum::messages.new_forum') : trans('forum::messages.edit_forum') }}</h2>

    @if(!empty($errors))
        <div class="errors">
            @foreach($errors as $fieldErrors)
                @foreach((array) $fieldErrors as $message)
                    <div>{{ $message }}</div>
                @endforeach
            @endforeach
        </div>
    @endif

    <form method="post" action="{{ $forum === null ? '/admin/forum/forums' : '/admin/forum/forums/' . $forum->id() }}">
        @csrf
        <div class="grid2">
            <div class="field">
                <label for="title">{{ trans('forum::messages.title') }}</label>
                <input type="text" id="title" name="title" required maxlength="190"
                       value="{{ $old['title'] ?? ($forum?->title() ?? '') }}">
            </div>
            <div class="field">
                <label for="slug">{{ trans('forum::messages.slug') }}</label>
                <input type="text" id="slug" name="slug" maxlength="200" dir="ltr"
                       value="{{ $old['slug'] ?? ($forum?->slug() ?? '') }}"
                       placeholder="{{ trans('forum::messages.slug_optional') }}">
            </div>
        </div>
        <div class="field">
            <label for="description">{{ trans('forum::messages.description') }}</label>
            <textarea id="description" name="description" style="min-height:4rem">{{ $old['description'] ?? ($forum?->description() ?? '') }}</textarea>
        </div>
        <div class="grid2">
            <div class="field">
                <label for="parent_id">{{ trans('forum::messages.parent_forum') }}</label>
                <select id="parent_id" name="parent_id">
                    <option value="">{{ trans('forum::messages.no_parent') }}</option>
                    @foreach($forums as $candidate)
                        @if($forum === null || (int) $candidate->id() !== (int) $forum->id())
                            <option value="{{ $candidate->id() }}"
                                {{ ((string) ($old['parent_id'] ?? ($forum?->parentId() ?? ''))) === (string) $candidate->id() ? 'selected' : '' }}>
                                {{ $candidate->title() }}
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="position">{{ trans('forum::messages.position') }}</label>
                <input type="number" id="position" name="position" min="0"
                       value="{{ $old['position'] ?? ($forum?->position() ?? 0) }}">
            </div>
        </div>
        <button class="btn" type="submit">{{ trans('forum::messages.save') }}</button>
        <a class="btn gray" href="/admin/forum/forums">{{ trans('forum::messages.cancel') }}</a>
    </form>
</div>
@endsection

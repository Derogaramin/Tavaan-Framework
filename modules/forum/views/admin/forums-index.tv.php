@extends('forum::admin.layout')

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
        <h2>{{ trans('forum::messages.admin_forums') }} <span class="muted">({{ trans_number(count($forums)) }})</span></h2>
        @if($canManage)
            <a class="btn" href="/admin/forum/forums/create">+ {{ trans('forum::messages.new_forum') }}</a>
        @endif
    </div>

    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>{{ trans('forum::messages.title') }}</th>
            <th>{{ trans('forum::messages.parent_forum') }}</th>
            <th>{{ trans('forum::messages.threads') }}</th>
            <th>{{ trans('forum::messages.posts') }}</th>
            <th>{{ trans('forum::messages.status') }}</th>
            @if($canManage)<th>{{ trans('forum::messages.actions') }}</th>@endif
        </tr>
        </thead>
        <tbody>
        @php $byId = []; foreach ($forums as $f) { $byId[(int) $f->id()] = $f; } @endphp
        @forelse($forums as $forum)
            <tr>
                <td>{{ trans_number((int) $forum->id()) }}</td>
                <td>
                    <a href="/forums/{{ $forum->slug() }}" target="_blank"><strong>{{ $forum->title() }}</strong></a>
                    <br><span class="muted" style="font-size:.78rem">/{{ $forum->slug() }}</span>
                </td>
                <td>{{ $forum->parentId() !== null ? ($byId[$forum->parentId()]?->title() ?? '—') : '—' }}</td>
                <td>{{ trans_number($forum->threadCount()) }}</td>
                <td>{{ trans_number($forum->postCount()) }}</td>
                <td>
                    @if($forum->isLocked())<span class="badge locked">🔒 {{ trans('forum::messages.locked') }}</span>
                    @else<span class="badge solved">{{ trans('forum::messages.open') }}</span>@endif
                </td>
                @if($canManage)
                    <td>
                        <div class="row-actions">
                            <a class="btn gray" href="/admin/forum/forums/{{ $forum->id() }}/edit">{{ trans('forum::messages.edit') }}</a>
                            <form method="post" action="/admin/forum/forums/{{ $forum->id() }}/lock" class="inline">
                                @csrf
                                <input type="hidden" name="locked" value="{{ $forum->isLocked() ? '0' : '1' }}">
                                <button class="btn amber" type="submit">
                                    {{ $forum->isLocked() ? trans('forum::messages.unlock') : trans('forum::messages.lock') }}
                                </button>
                            </form>
                            <form method="post" action="/admin/forum/forums/{{ $forum->id() }}" class="inline"
                                  onsubmit="return confirm('{{ trans('forum::messages.delete_confirm') }}');">
                                @csrf
                                @method('DELETE')
                                <button class="btn red" type="submit">{{ trans('forum::messages.delete') }}</button>
                            </form>
                        </div>
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="7" class="muted">{{ trans('forum::messages.no_forums') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

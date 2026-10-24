@extends('forum::admin.layout')

@section('content')
<div class="card">
    <h2>🚩 {{ trans('forum::messages.admin_moderation') }} <span class="muted">({{ trans_number($total) }})</span></h2>

    @forelse($queue as $flag)
        @php
            $flaggedPost = $posts[$flag->postId()] ?? null;
            $reporterName = $flag->userId() !== null ? ($names[(string) $flag->userId()] ?? ('#' . $flag->userId())) : trans('forum::messages.guest');
            $authorName = $flaggedPost !== null ? ($names[(string) $flaggedPost->authorId()] ?? ('#' . $flaggedPost->authorId())) : '—';
        @endphp
        <div style="border:1px solid var(--b);border-radius:10px;padding:1rem;margin-block-end:1rem">
            <div class="row-actions" style="justify-content:space-between">
                <span class="badge pending">{{ trans('forum::messages.flag_pending') }}</span>
                <span class="muted" style="font-size:.8rem">{{ jalali($flag->createdAt(), 'Y/m/d H:i') }}</span>
            </div>

            @if($flaggedPost !== null)
                <p class="muted" style="margin-block:.5rem">
                    {{ trans('forum::messages.flag_reporter') }}: <strong>{{ $reporterName }}</strong>
                    • {{ trans('forum::messages.flag_author') }}: <strong>{{ $authorName }}</strong>
                    @if($flag->reason())
                        <span class="flag-reason">«{{ $flag->reason() }}»</span>
                    @endif
                </p>
                <div style="background:#f8fafc;border-radius:8px;padding:.7rem 1rem;font-size:.9rem">
                    {{ mb_substr(strip_tags($flaggedPost->body()), 0, 400) }}
                    @if(!$flaggedPost->isVisible())
                        <span class="badge locked">{{ trans('forum::messages.status_' . $flaggedPost->status()->value) }}</span>
                    @endif
                </div>
                <p style="margin-block:.5rem 0">
                    <a href="/threads/{{ $flaggedPost->threadId() }}#post-{{ $flaggedPost->id() }}" target="_blank">
                        {{ trans('forum::messages.view_in_thread') }} ↗
                    </a>
                </p>
            @else
                <p class="muted">{{ trans('forum::messages.post_removed') }}</p>
            @endif

            <div class="row-actions" style="margin-block-start:.6rem">
                <form method="post" action="/admin/forum/moderation/{{ $flag->id() }}/resolve" class="inline">
                    @csrf
                    <button class="btn green" type="submit">{{ trans('forum::messages.resolve_hide') }}</button>
                </form>
                <form method="post" action="/admin/forum/moderation/{{ $flag->id() }}/dismiss" class="inline">
                    @csrf
                    <button class="btn gray" type="submit">{{ trans('forum::messages.dismiss') }}</button>
                </form>
                @if($canBan && $flaggedPost !== null)
                    <form method="post" action="/admin/forum/users/{{ $flaggedPost->authorId() }}/ban" class="inline"
                          onsubmit="return confirm('{{ trans('forum::messages.ban_confirm') }}');">
                        @csrf
                        <input type="number" name="days" value="7" min="0" max="3650" style="width:5.5rem;display:inline-block"
                               title="{{ trans('forum::messages.ban_days') }}">
                        <button class="btn red" type="submit">{{ trans('forum::messages.ban_author') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <p class="muted">{{ trans('forum::messages.queue_empty') }}</p>
    @endforelse

    @php $totalPages = (int) max(1, ceil($total / max(1, $perPage))); @endphp
    @if($totalPages > 1)
        <div class="pagination">
            @for($p = 1; $p <= $totalPages; $p++)
                @if($p === $page)
                    <span class="current">{{ trans_number($p) }}</span>
                @else
                    <a href="/admin/forum/moderation?page={{ $p }}">{{ trans_number($p) }}</a>
                @endif
            @endfor
        </div>
    @endif
</div>
@endsection

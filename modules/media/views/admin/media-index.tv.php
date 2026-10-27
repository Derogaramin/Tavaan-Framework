@extends('media::admin.layout')

@section('content')
@if(is_string($ok ?? null) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif

@if(!empty($errors))
    <div class="errors">
        @foreach($errors as $fieldErrors)
            @foreach((array) $fieldErrors as $err)
                <div>{{ $err }}</div>
            @endforeach
        @endforeach
    </div>
@endif

<div class="card">
    <h2>{{ trans('media::messages.ui.admin_title') }} <span class="muted">({{ trans_number($total) }})</span></h2>

    <form method="post" action="/admin/media" enctype="multipart/form-data" class="upload-box">
        @csrf
        <input type="file" name="media_file" required>
        <button class="btn" type="submit">{{ trans('media::messages.ui.upload') }}</button>
        <span class="muted" style="font-size:.8rem">{{ trans('media::messages.ui.upload_hint') }}</span>
    </form>
</div>

<div class="card">
    <form method="get" action="/admin/media" class="toolbar">
        <input type="search" name="q" value="{{ $search }}" placeholder="{{ trans('media::messages.ui.search_placeholder') }}">
        <select name="type">
            <option value="">{{ trans('media::messages.ui.all_types') }}</option>
            <option value="image" {{ $type === 'image' ? 'selected' : '' }}>{{ trans('media::messages.ui.type_image') }}</option>
            <option value="video" {{ $type === 'video' ? 'selected' : '' }}>{{ trans('media::messages.ui.type_video') }}</option>
            <option value="audio" {{ $type === 'audio' ? 'selected' : '' }}>{{ trans('media::messages.ui.type_audio') }}</option>
            <option value="doc" {{ $type === 'doc' ? 'selected' : '' }}>{{ trans('media::messages.ui.type_doc') }}</option>
        </select>
        <button class="btn gray" type="submit">🔍</button>
    </form>

    @php
        $iconFor = static function (string $mime): string {
            if (str_starts_with($mime, 'image/')) { return '🖼️'; }
            if (str_starts_with($mime, 'video/')) { return '🎬'; }
            if (str_starts_with($mime, 'audio/')) { return '🎧'; }
            if (str_contains($mime, 'pdf')) { return '📕'; }
            if (str_contains($mime, 'zip') || str_contains($mime, 'rar')) { return '🗜️'; }
            return '📄';
        };
        $formatSize = static function (int $bytes): string {
            if ($bytes >= 1048576) { return trans_number(round($bytes / 1048576, 1), 1) . ' MB'; }
            if ($bytes >= 1024) { return trans_number(round($bytes / 1024, 1), 1) . ' KB'; }
            return trans_number($bytes) . ' B';
        };
    @endphp

    @if(count($items) === 0)
        <p class="muted">{{ trans('media::messages.ui.no_media') }}</p>
    @else
    <div class="media-grid">
        @foreach($items as $m)
            <div class="media-card">
                <div class="media-thumb">
                    @if(str_starts_with($m->mimeType, 'image/'))
                        <img src="{{ $m->url() }}" alt="{{ $m->originalName ?? $m->id }}" loading="lazy">
                    @else
                        <span>{{ $iconFor($m->mimeType) }}</span>
                    @endif
                </div>
                <div class="media-body">
                    <div class="media-name" title="{{ $m->originalName ?? $m->path }}">{{ $m->originalName ?? basename($m->path) }}</div>
                    <div class="muted">{{ $formatSize($m->size) }}
                        @if($m->width !== null && $m->height !== null)
                            • {{ trans_number($m->width) }}×{{ trans_number($m->height) }}
                        @endif
                    </div>
                    <div class="muted">{{ $m->createdAt !== null ? jalali($m->createdAt, 'Y/m/d') : '' }}</div>
                </div>
                <div class="media-actions">
                    <a class="btn gray tiny" href="{{ $m->url() }}" target="_blank">{{ trans('media::messages.ui.download') }}</a>
                    <form class="inline" method="post" action="/admin/media/{{ $m->id }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn red tiny" type="submit" onclick="return confirm('{{ trans('media::messages.ui.confirm_delete') }}')">{{ trans('media::messages.ui.delete') }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    @php
        $lastPage = (int) max(1, ceil($total / max(1, $perPage)));
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $pageUrl = static function (int $p) use ($queryString): string {
            $params = [];
            parse_str($queryString, $params);
            $params['page'] = $p;
            return '/admin/media?' . http_build_query($params);
        };
    @endphp
    @if($lastPage > 1)
    <nav class="pagination">
        @if($page > 1)<a href="{{ $pageUrl($page - 1) }}">‹</a>@endif
        @for($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++)
            @if($p === $page)
                <span class="current">{{ trans_number($p) }}</span>
            @else
                <a href="{{ $pageUrl($p) }}">{{ trans_number($p) }}</a>
            @endif
        @endfor
        @if($page < $lastPage)<a href="{{ $pageUrl($page + 1) }}">›</a>@endif
    </nav>
    @endif
    @endif
</div>
@endsection

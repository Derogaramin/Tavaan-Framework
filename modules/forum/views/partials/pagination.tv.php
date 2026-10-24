@php
    $totalPages = (int) \max(1, \ceil($total / \max(1, $perPage)));
    $qs = static function (int $p) use ($baseUrl): string {
        return $baseUrl . '?page=' . $p;
    };
@endphp
@if($totalPages > 1)
<nav class="forum-pagination">
    @if($page > 1)
        <a href="{{ $qs($page - 1) }}">{{ trans('forum::messages.prev_page') }}</a>
    @endif
    @for($p = 1; $p <= $totalPages; $p++)
        @if($p === $page)
            <span class="current">{{ trans_number($p) }}</span>
        @elseif(\abs($p - $page) <= 2 || $p === 1 || $p === $totalPages)
            <a href="{{ $qs($p) }}">{{ trans_number($p) }}</a>
        @endif
    @endfor
    @if($page < $totalPages)
        <a href="{{ $qs($page + 1) }}">{{ trans('forum::messages.next_page') }}</a>
    @endif
</nav>
@endif

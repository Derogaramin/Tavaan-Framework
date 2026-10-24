@php
    $lastPage = (int) max(1, (int) ceil($total / max(1, $perPage)));
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $baseUrl = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/blog';
    $pageUrl = static function (int $p) use ($baseUrl, $queryString): string {
        $params = [];
        parse_str($queryString, $params);
        $params['page'] = $p;
        return $baseUrl . '?' . http_build_query($params);
    };
@endphp
@if($lastPage > 1)
<nav class="blog-pagination" aria-label="صفحه‌بندی">
    @if($page > 1)
        <a href="{{ $pageUrl($page - 1) }}">‹ {{ trans('blog::messages.prev') }}</a>
    @endif
    @for($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++)
        @if($p === $page)
            <span class="current">{{ trans_number($p) }}</span>
        @else
            <a href="{{ $pageUrl($p) }}">{{ trans_number($p) }}</a>
        @endif
    @endfor
    @if($page < $lastPage)
        <a href="{{ $pageUrl($page + 1) }}">{{ trans('blog::messages.next') }} ›</a>
    @endif
</nav>
@endif

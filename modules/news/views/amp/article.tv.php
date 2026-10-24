<!doctype html>
<html ⚡ lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $article->title() }}</title>
    <link rel="canonical" href="{{ $canonical }}">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <script async src="https://cdn.ampproject.org/v0.js"></script>
    @if($article->lead() !== null && $article->lead() !== '')
    <meta name="description" content="{{ $article->lead() }}">
    @endif
    <script type="application/ld+json">@json($jsonLd)</script>
    @verbatim
    <style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style>
    <noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
    <style amp-custom>
        body { font-family: Vazirmatn, Tahoma, system-ui, sans-serif; margin: 0; padding: 1rem; line-height: 1.9; color: #111827; background: #fff; }
        h1 { font-size: 1.4rem; line-height: 1.6; }
        .meta { color: #6b7280; font-size: .85rem; }
        .lead { font-weight: 600; color: #374151; }
        .body img { max-width: 100%; height: auto; }
        footer { margin-block-start: 2rem; color: #6b7280; font-size: .8rem; }
        a { color: #b91c1c; }
    </style>
    @endverbatim
</head>
<body>
    <header>
        <a href="/news">📰 {{ trans('news::messages.news_title') }}</a>
    </header>

    <main>
        <article>
            <h1>{{ $article->title() }}</h1>
            <div class="meta">
                @if($article->publishedAt() !== null)
                    <time datetime="{{ $article->publishedAt()->format('Y-m-d\TH:i:sP') }}">{{ jalali($article->publishedAt(), 'Y/m/d H:i') }}</time>
                @endif
                @if(isset($author) && $author !== null)
                    — {{ trans('news::messages.by_author', ['name' => $author->displayName()]) }}
                @endif
            </div>

            @if($article->lead() !== null && $article->lead() !== '')
                <p class="lead">{{ $article->lead() }}</p>
            @endif

            @if($imageUrl !== null)
                <amp-img src="{{ $imageUrl }}" width="800" height="450" layout="responsive" alt="{{ $article->title() }}"></amp-img>
            @endif

            @php
                // AMP: <img> خام مجاز نیست → تبدیل به amp-img با ابعاد پیش‌فرض
                $ampBody = (string) preg_replace(
                    '/<img\s+([^>]*?)src="([^"]+)"([^>]*?)\/?>/iu',
                    '<amp-img src="$2" width="640" height="360" layout="responsive" $1 $3></amp-img>',
                    (string) $article->body(),
                );
                // AMP: style درون‌خطی مجاز نیست
                $ampBody = (string) preg_replace('/\s+style="[^"]*"/iu', '', $ampBody);
            @endphp
            <div class="body">{!! $ampBody !!}</div>
        </article>
    </main>

    <footer>
        <a href="{{ $canonical }}">{{ trans('news::messages.desktop_version') }}</a>
    </footer>
</body>
</html>

<section class="news-card" id="comments">
    <h3>{{ trans('news::messages.comments') }}</h3>

    @if(empty($thread))
        <p class="muted">{{ trans('news::messages.no_comments') }}</p>
    @else
        @php
            $renderNodes = function (array $nodes) use (&$renderNodes): string {
                $html = '';
                foreach ($nodes as $node) {
                    $c = $node->comment;
                    $name = htmlspecialchars($c->authorName, ENT_QUOTES, 'UTF-8');
                    $body = nl2br(htmlspecialchars($c->body, ENT_QUOTES, 'UTF-8'));
                    $date = $c->createdAt !== null ? jalali($c->createdAt, 'Y/m/d H:i') : '';
                    $html .= '<div class="comment">';
                    $html .= '<div class="news-meta"><strong>' . $name . '</strong><span class="comment-meta">' . $date . '</span></div>';
                    $html .= '<div>' . $body . '</div>';
                    if ($node->children !== []) {
                        $html .= '<div class="comment-children">' . $renderNodes($node->children) . '</div>';
                    }
                    $html .= '</div>';
                }
                return $html;
            };
        @endphp
        {!! $renderNodes($thread) !!}
    @endif
</section>

@if($commentsEnabled)
<section class="news-card" id="comment-form">
    <h3>{{ trans('news::messages.comment_form_title') }}</h3>

    @if(is_array($flash ?? null))
        @if(($flash['status'] ?? '') === 'ok')
            <div class="news-flash ok">{{ $flash['message'] ?? '' }}</div>
        @elseif(($flash['status'] ?? '') === 'error')
            <div class="news-flash error">
                @foreach(($flash['errors'] ?? []) as $messages)
                    @foreach((array) $messages as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                @endforeach
            </div>
        @endif
    @endif

    <form method="post" action="/news/{{ $article->slug() }}/comments" class="news-form">
        @csrf
        <input type="hidden" name="parent_id" value="">
        @guest
        <div class="field">
            <label for="author_name">{{ trans('news::messages.your_name') }}</label>
            <input type="text" id="author_name" name="author_name" required>
        </div>
        <div class="field">
            <label for="author_email">{{ trans('news::messages.your_email') }}</label>
            <input type="email" id="author_email" name="author_email" required>
        </div>
        @endguest
        <div class="field">
            <label for="body">{{ trans('news::messages.comment_body') }}</label>
            <textarea id="body" name="body" required>{{ is_array($flash ?? null) ? ($flash['old']['body'] ?? '') : '' }}</textarea>
        </div>
        <button class="news-btn" type="submit">{{ trans('news::messages.comment_submit') }}</button>
    </form>
</section>
@else
<section class="news-card">
    <p class="muted">{{ trans('comment::messages.closed') }}</p>
</section>
@endif

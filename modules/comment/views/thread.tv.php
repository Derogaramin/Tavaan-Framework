<!--
    ماژول نظر — رشتهٔ نظرات embeddable (لیست + فرم ثبت).

    ورودی‌ها: $type, $subjectId, $nodes (list<ThreadNode>), $canComment,
    $guestAllowed, $errors, $old, $ok
    مصرف: include('comment::thread') از ویوی هر ماژول/اپلیکیشن.
-->

@verbatim
<style>
    .tvn-comments { --tc-p: #2563eb; --tc-b: #e2e8f0; --tc-m: #64748b; line-height: 1.8; }
    .tvn-comments .comment { border: 1px solid var(--tc-b); border-radius: 10px; padding: .7rem .9rem; margin-block-end: .6rem; background: #fff; }
    .tvn-comments .comment-meta { color: var(--tc-m); font-size: .78rem; margin-inline-start: .5rem; }
    .tvn-comments .comment-children { margin-inline-start: 1.4rem; margin-block-start: .5rem; border-inline-start: 2px solid var(--tc-b); padding-inline-start: .7rem; }
    .tvn-comments .tvn-form label { display: block; font-weight: 600; font-size: .85rem; margin-block-end: .2rem; }
    .tvn-comments .tvn-form input[type=text], .tvn-comments .tvn-form input[type=email], .tvn-comments .tvn-form textarea {
        width: 100%; padding: .5rem .65rem; border: 1px solid var(--tc-b); border-radius: 8px; font-family: inherit; font-size: .9rem; box-sizing: border-box;
    }
    .tvn-comments .tvn-form textarea { min-height: 7rem; }
    .tvn-comments .tvn-field { margin-block-end: .8rem; }
    .tvn-comments .tvn-btn { background: var(--tc-p); color: #fff; border: 0; border-radius: 8px; padding: .5rem 1.2rem; font-family: inherit; font-size: .9rem; cursor: pointer; }
    .tvn-comments .tvn-errors { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: .6rem 1rem; margin-block-end: .8rem; font-size: .85rem; }
    .tvn-comments .tvn-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; padding: .6rem 1rem; margin-block-end: .8rem; font-size: .85rem; }
    .tvn-comments .muted { color: var(--tc-m); }
</style>
@endverbatim
<section class="tvn-comments" id="comments">
    <h3>{{ trans('comment::messages.ui.comments') }}</h3>

    @if(is_string($ok ?? null) && $ok !== '')
        <div class="tvn-ok">{{ $ok }}</div>
    @endif

    @if(empty($nodes))
        <p class="muted">{{ trans('comment::messages.ui.no_comments') }}</p>
    @else
        @php
            $renderNodes = function (array $nodes) use (&$renderNodes): string {
                $html = '';
                foreach ($nodes as $node) {
                    $c = $node->comment;
                    $name = htmlspecialchars($c->authorName, ENT_QUOTES, 'UTF-8');
                    $body = nl2br(htmlspecialchars($c->body, ENT_QUOTES, 'UTF-8'));
                    $date = $c->createdAt !== null ? jalali($c->createdAt, 'Y/m/d H:i') : '';
                    $id = htmlspecialchars($c->id, ENT_QUOTES, 'UTF-8');
                    $html .= '<div class="comment" id="comment-' . $id . '">';
                    $html .= '<div><strong>' . $name . '</strong><span class="comment-meta">' . $date . '</span></div>';
                    $html .= '<div>' . $body . '</div>';
                    if ($node->children !== []) {
                        $html .= '<div class="comment-children">' . $renderNodes($node->children) . '</div>';
                    }
                    $html .= '</div>';
                }
                return $html;
            };
        @endphp
        {!! $renderNodes($nodes) !!}
    @endif

    @if(!empty($canComment))
    <div id="comment-form" style="margin-block-start:1.2rem">
        <h4>{{ trans('comment::messages.ui.form_title') }}</h4>

        @if(!empty($errors))
            <div class="tvn-errors">
                @foreach($errors as $fieldErrors)
                    @foreach((array) $fieldErrors as $err)
                        <div>{{ $err }}</div>
                    @endforeach
                @endforeach
            </div>
        @endif

        <form method="post" action="/comments/{{ $type }}/{{ $subjectId }}" class="tvn-form">
            @csrf
            @if(!empty($guestAllowed) && !\Tavaan\Core\Facade\Facades\Auth::check())
            <div class="tvn-field">
                <label for="tvn-author-name">{{ trans('comment::messages.ui.name') }}</label>
                <input type="text" id="tvn-author-name" name="author_name" value="{{ $old['author_name'] ?? '' }}" required>
            </div>
            <div class="tvn-field">
                <label for="tvn-author-email">{{ trans('comment::messages.ui.email') }}</label>
                <input type="email" id="tvn-author-email" name="author_email" value="{{ $old['author_email'] ?? '' }}" dir="ltr" required>
            </div>
            @endif
            <div class="tvn-field">
                <label for="tvn-body">{{ trans('comment::messages.ui.body') }}</label>
                <textarea id="tvn-body" name="body" required>{{ $old['body'] ?? '' }}</textarea>
            </div>
            <input type="hidden" name="parent_id" value="{{ $old['parent_id'] ?? '' }}">
            <button class="tvn-btn" type="submit">{{ trans('comment::messages.ui.submit') }}</button>
        </form>
    </div>
    @else
        <p class="muted">{{ trans('comment::messages.closed') }}</p>
    @endif
</section>

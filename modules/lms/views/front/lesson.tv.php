@extends('lms::layouts.lms')

@php
    $isDone = isset($completed[$lesson->id()]);
@endphp

@section('pageTitle', $lesson->title() . ' — ' . $course->title())

@section('content')
<div class="lms-grid cols-2">
    <div>
        <div class="lms-card">
            <p class="muted">
                <a href="/lms/learn/{{ $course->slug() }}">← {{ trans('lms::messages.learning_space') }}</a>
            </p>
            <h1>{{ $lesson->type()->icon() }} {{ $lesson->title() }}</h1>
            <p class="muted">
                {{ $lesson->type()->labelFa() }}
                @if($lesson->duration() > 0)
                    • {{ trans('lms::messages.duration_minutes', ['n' => trans_number($lesson->duration())]) }}
                @endif
                @if($isDone)
                    • <span class="badge done">{{ trans('lms::messages.lesson_completed') }}</span>
                @endif
                @if(!$hasAccess && $lesson->isFreePreview())
                    • <span class="badge free">{{ trans('lms::messages.free_preview') }}</span>
                @endif
            </p>

            @if($lesson->type() === \Modules\Lms\Domain\Enums\LessonType::Video && $lesson->videoUrl() !== null)
                <p>
                    <video controls preload="metadata" style="width:100%;border-radius:10px;background:#000"
                           src="{{ $lesson->videoUrl() }}">
                    </video>
                </p>
                <p class="muted">{{ trans('lms::messages.video_lesson') }}: <a href="{{ $lesson->videoUrl() }}" rel="noopener">{{ $lesson->videoUrl() }}</a></p>
            @endif

            @if($lesson->content() !== null && $lesson->content() !== '')
                <div>{!! $lesson->content() !!}</div>
            @endif

            @if($lesson->type() === \Modules\Lms\Domain\Enums\LessonType::Attachment && $lesson->attachmentPath() !== null)
                <p><a class="lms-btn ghost" href="/storage/{{ ltrim($lesson->attachmentPath(), '/') }}">⬇ {{ trans('lms::messages.download_attachment') }}</a></p>
            @endif

            @if($quiz !== null)
                <p style="margin-top:1rem">
                    <a class="lms-btn" href="/lms/quizzes/{{ $quiz->id() }}">📝 {{ $quiz->title() }}</a>
                    @if($attempt !== null && $attempt->passed() === true)
                        <span class="badge done">{{ trans('lms::messages.quiz_passed') }}</span>
                    @endif
                </p>
            @endif

            @if($hasAccess && !$isDone && $lesson->type() !== \Modules\Lms\Domain\Enums\LessonType::Quiz)
                <form method="post" action="/lms/lessons/{{ $lesson->id() }}/complete" style="margin-top:1rem">
                    @csrf
                    <button class="lms-btn success" type="submit">✔ {{ trans('lms::messages.mark_complete') }}</button>
                </form>
            @endif
        </div>

        @if($qaEnabled)
            <div class="lms-card">
                <h2>{{ trans('lms::messages.qa') }}</h2>

                @if(count($qaThread) > 0)
                    @foreach($qaThread as $node)
                        <div class="qa-item">
                            <span class="who">{{ $node->comment->authorName }}</span>
                            <span class="muted">{{ jalali($node->comment->createdAt, 'Y/m/d H:i') }}</span>
                            <p>{{ $node->comment->body }}</p>
                            @foreach($node->children as $child)
                                <div class="qa-item qa-reply">
                                    <span class="who">{{ $child->comment->authorName }}</span>
                                    <span class="muted">{{ jalali($child->comment->createdAt, 'Y/m/d H:i') }}</span>
                                    <p>{{ $child->comment->body }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @else
                    <p class="muted">{{ trans('lms::messages.qa_empty') }}</p>
                @endif

                @if($canAsk)
                    <form method="post" action="/lms/lessons/{{ $lesson->id() }}/questions" class="lms-form" style="margin-top:1rem">
                        @csrf
                        <div class="field">
                            <label for="body">{{ trans('lms::messages.qa_ask') }}</label>
                            <textarea id="body" name="body" required maxlength="5000" placeholder="{{ trans('lms::messages.qa_body') }}"></textarea>
                        </div>
                        <button class="lms-btn ghost" type="submit">{{ trans('lms::messages.qa_submit') }}</button>
                    </form>
                @else
                    <p class="muted">{{ trans('lms::messages.qa_login_hint') }}</p>
                @endif
            </div>
        @endif
    </div>

    <aside>
        <div class="lms-card">
            <h3><a href="/lms/learn/{{ $course->slug() }}">{{ $course->title() }}</a></h3>
            @foreach($structure as $branch)
                <p class="muted" style="margin:.4rem 0 .1rem"><strong>📁 {{ $branch['section']->title() }}</strong></p>
                @foreach($branch['lessons'] as $item)
                    <div style="font-size:.85rem; {{ $item->id() === $lesson->id() ? 'font-weight:800' : '' }}">
                        {{ isset($completed[$item->id()]) ? '✅' : '○' }}
                        <a href="/lms/lessons/{{ $item->id() }}">{{ $item->title() }}</a>
                    </div>
                @endforeach
            @endforeach
        </div>
    </aside>
</div>
@endsection

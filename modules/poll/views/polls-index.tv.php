@extends('poll::layouts.poll')

@section('pageTitle'){{ trans('poll::messages.polls_title') }}@endsection

@section('content')
<h1>{{ trans('poll::messages.polls_title') }}</h1>

@forelse($polls as $poll)
    <article class="pl-card">
        <h2 style="margin-block:0 0.35rem;font-size:1.15rem">
            <a href="/polls/{{ $poll->slug() }}">{{ $poll->question() }}</a>
        </h2>
        <div class="pl-meta">
            <span class="badge open">{{ trans('poll::status.open') }}</span>
            @if($poll->multiple())
                <span>{{ trans('poll::messages.multiple_hint') }}</span>
            @endif
            @if($poll->endsAt() !== null)
                <span>{{ trans('poll::messages.ends_at_label', ['date' => jalali($poll->endsAt(), 'Y/m/d H:i')]) }}</span>
            @endif
        </div>
    </article>
@empty
    <div class="pl-card">
        <p class="muted">{{ trans('poll::messages.no_polls') }}</p>
    </div>
@endforelse
@endsection

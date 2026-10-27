@extends('poll::layouts.poll')

@section('pageTitle'){{ $poll->question() }}@endsection

@section('content')
<article class="pl-card">
    <h1 style="font-size:1.3rem">{{ $poll->question() }}</h1>
    <div class="pl-meta" style="margin-block-end:.75rem">
        <span class="badge {{ $poll->status()->value }}">{{ trans('poll::status.' . $poll->status()->value) }}</span>
        @if($poll->endsAt() !== null)
            <span>{{ trans('poll::messages.ends_at_label', ['date' => jalali($poll->endsAt(), 'Y/m/d H:i')]) }}</span>
        @endif
        @if($results !== null)
            <span>{{ trans('poll::messages.total_votes', ['count' => trans_number($results['total'])]) }}</span>
        @endif
    </div>

    @if(is_string($ok ?? null) && $ok !== '')
        <div class="pl-flash ok">{{ $ok }}</div>
    @endif
    @if(!empty($errors))
        <div class="pl-flash error">
            @foreach($errors as $field => $messages)
                @foreach((array) $messages as $message)
                    <div>• {{ $message }}</div>
                @endforeach
            @endforeach
        </div>
    @endif

    @if($canVote)
        <form method="post" action="/polls/{{ $poll->slug() }}/vote" class="pl-form">
            @csrf
            @if($poll->multiple())
                <p class="muted">{{ trans('poll::messages.multiple_hint') }}</p>
                @foreach($poll->options() as $index => $label)
                    <label class="option-line">
                        <input type="checkbox" name="options[]" value="{{ $index }}">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            @else
                @foreach($poll->options() as $index => $label)
                    <label class="option-line">
                        <input type="radio" name="option" value="{{ $index }}">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            @endif
            <button class="pl-btn" type="submit">{{ trans('poll::messages.vote_submit') }}</button>
        </form>
    @elseif($results !== null)
        @if($hasVoted)
            <div class="pl-flash ok">{{ trans('poll::messages.you_voted') }}</div>
        @endif

        <h2 style="font-size:1.05rem">{{ trans('poll::messages.results_title') }}</h2>
        @foreach($results['rows'] as $row)
            <div class="result-row {{ $results['leader'] !== null && $row['index'] === $results['leader'] ? 'leader' : '' }}">
                <div class="result-label">
                    <span>{{ $row['label'] }}</span>
                    <span class="muted">{{ trans_number($row['count']) }} ({{ trans_number((string) $row['percent']) }}٪)</span>
                </div>
                <div class="result-bar">
                    <div class="result-fill" style="width: {{ min(100, max(0, (float) $row['percent'])) }}%"></div>
                </div>
            </div>
        @endforeach
    @elseif(!$poll->isVotable())
        <p class="muted">{{ trans('poll::messages.closed_notice') }}</p>
    @endif
</article>

<p><a href="/polls">← {{ trans('poll::messages.back_to_list') }}</a></p>
@endsection

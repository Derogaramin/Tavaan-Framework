@extends('faq::layouts.faq')

@section('pageTitle'){{ trans('faq::messages.index_title') }}@endsection

@section('content')
<h1>{{ trans('faq::messages.faqs_title') }}</h1>

@if(is_string($ok ?? null) && $ok !== '')
    <div class="fq-flash ok">{{ $ok }}</div>
@endif
@if(is_string($error ?? null) && $error !== '')
    <div class="fq-flash error">{{ $error }}</div>
@endif

<form class="fq-search" method="get" action="/faq">
    <label class="muted" for="q" style="align-self:center">{{ trans('faq::messages.search_label') }}</label>
    <input type="search" id="q" name="q" value="{{ $q ?? '' }}" placeholder="{{ trans('faq::messages.search_placeholder') }}">
    @if(($cat ?? null) !== null)
        <input type="hidden" name="cat" value="{{ $cat->slug() }}">
    @endif
    <button class="fq-btn" type="submit">{{ trans('faq::messages.search_action') }}</button>
</form>

@if(count($categories ?? []) > 0)
<nav class="fq-chips">
    <a class="fq-chip {{ ($cat ?? null) === null ? 'active' : '' }}" href="/faq{{ ($q ?? '') !== '' ? '?q=' . urlencode($q) : '' }}">{{ trans('faq::messages.filter_all') }}</a>
    @foreach($categories as $fqCat)
        <a class="fq-chip {{ ($cat ?? null) !== null && $cat->id() === $fqCat->id() ? 'active' : '' }}"
           href="/faq?cat={{ $fqCat->slug() }}{{ ($q ?? '') !== '' ? '&q=' . urlencode($q) : '' }}">{{ $fqCat->title() }}</a>
    @endforeach
</nav>
@endif

@php
    $fqSections = [];
    foreach ($categories ?? [] as $fqCat) {
        $fqGroup = $groups[$fqCat->id()] ?? [];
        if (count($fqGroup) > 0) {
            $fqSections[] = ['title' => $fqCat->title(), 'items' => $fqGroup];
        }
    }
    if (count($uncategorized ?? []) > 0) {
        $fqSections[] = ['title' => trans('faq::messages.uncategorized'), 'items' => $uncategorized];
    }
@endphp

@if(count($fqSections) === 0)
    <div class="fq-card">
        <p class="muted">{{ ($q ?? '') !== '' ? trans('faq::messages.no_results') : trans('faq::messages.no_faqs') }}</p>
    </div>
@else
    @foreach($fqSections as $fqSection)
        <h2 class="fq-cat-title">{{ $fqSection['title'] }}</h2>
        @foreach($fqSection['items'] as $item)
            <details class="fq-item" id="q-{{ $item->id() }}">
                <summary>{{ $item->question() }}</summary>
                <div class="fq-answer">{!! $item->answer() !!}</div>
                @if(!empty($helpfulEnabled))
                <form class="fq-helpful" method="post" action="/faq/{{ $item->id() }}/helpful">
                    @csrf
                    <span>{{ trans('faq::messages.helpful_prompt') }}</span>
                    <button class="fq-btn ghost" type="submit" name="value" value="yes">{{ trans('faq::messages.helpful_yes') }}</button>
                    <button class="fq-btn ghost" type="submit" name="value" value="no">{{ trans('faq::messages.helpful_no') }}</button>
                    <span class="muted">{{ trans_number($item->helpfulYes()) }} / {{ trans_number($item->helpfulNo()) }}</span>
                </form>
                @endif
            </details>
        @endforeach
    @endforeach
@endif
@endsection

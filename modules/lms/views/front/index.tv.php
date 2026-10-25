@extends('lms::layouts.lms')

@section('pageTitle', trans('lms::messages.catalog') . ' — ' . trans('lms::messages.lms_title'))

@section('content')
<div class="lms-grid cols-2">
    <div>
        <h1>{{ isset($category) ? trans('lms::messages.category_courses', ['title' => $category->title()]) : trans('lms::messages.catalog') }}</h1>
        @if(!isset($category))
            <p class="muted">{{ trans('lms::messages.catalog_subtitle') }}</p>
        @endif

        <div class="lms-grid cols-3">
            @forelse($courses as $course)
                @include('lms::partials.course-card', ['course' => $course, 'instructorName' => $instructors[$course->id()] ?? ''])
            @empty
                <div class="lms-card"><p class="muted">{{ trans('lms::messages.no_courses') }}</p></div>
            @endforelse
        </div>

        @include('lms::partials.pagination', ['baseUrl' => isset($category) ? '/lms/category/' . $category->slug() : '/lms', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
    </div>

    <aside>
        <div class="lms-card">
            <form method="get" action="/lms/search" class="lms-form">
                <div class="field">
                    <label for="q">{{ trans('lms::messages.search') }}</label>
                    <input type="text" id="q" name="q" placeholder="{{ trans('lms::messages.search_placeholder') }}">
                </div>
                <button class="lms-btn" type="submit">{{ trans('lms::messages.search_submit') }}</button>
            </form>
        </div>

        <div class="lms-card">
            <strong>{{ trans('lms::messages.categories') }}</strong>
            <ul>
                @foreach($categories as $cat)
                    <li><a href="/lms/category/{{ $cat->slug() }}">{{ $cat->title() }}</a></li>
                @endforeach
            </ul>
        </div>

        <div class="lms-card">
            <strong>{{ trans('lms::messages.verify_certificate') }}</strong>
            <p class="muted">{{ trans('lms::messages.certificate_verify_search') }}</p>
            <a class="lms-btn ghost sm" href="/lms/verify-certificate">{{ trans('lms::messages.verify_certificate') }}</a>
        </div>
    </aside>
</div>
@endsection

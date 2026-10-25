@extends('lms::layouts.lms')

@section('pageTitle', trans('lms::messages.search') . ' — ' . trans('lms::messages.lms_title'))

@section('content')
<h1>{{ trans('lms::messages.search_results', ['query' => $query]) }}</h1>

<div class="lms-grid cols-3">
    @forelse($courses as $course)
        @include('lms::partials.course-card', ['course' => $course, 'instructorName' => ''])
    @empty
        <div class="lms-card"><p class="muted">{{ trans('lms::messages.no_results') }}</p></div>
    @endforelse
</div>

@include('lms::partials.pagination', ['baseUrl' => '/lms/search?q=' . rawurlencode($query), 'total' => $total, 'page' => $page, 'perPage' => $perPage])
@endsection

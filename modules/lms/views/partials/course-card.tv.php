@php
    $instructorName = $instructorName ?? '';
@endphp
<div class="lms-card">
    <h3><a href="/lms/courses/{{ $course->slug() }}">{{ $course->title() }}</a></h3>
    @if($course->summary())
        <p class="muted">{!! $course->summary() !!}</p>
    @endif
    <p class="muted">
        <span class="badge level">{{ $course->level()->labelFa() }}</span>
        @if($course->isFree())
            <span class="badge free">{{ trans('lms::messages.free') }}</span>
        @else
            <span class="badge paid">{{ trans_number($course->price()->minor) }} {{ $course->price()->currency === 'IRR' ? 'ریال' : $course->price()->currency }}</span>
        @endif
    </p>
    <p class="muted">
        @if($instructorName !== '')👤 {{ $instructorName }} •@endif
        ⏱ {{ trans('lms::messages.duration_minutes', ['n' => trans_number($course->totalDuration())]) }} •
        🧑‍🎓 {{ trans('lms::messages.student_count', ['n' => trans_number($course->studentCount())]) }}
    </p>
</div>

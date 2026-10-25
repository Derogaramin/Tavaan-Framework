@extends('lms::layouts.admin')

@php
    $isEdit = $course !== null;
    $action = $isEdit ? '/admin/lms/courses/' . $course->id() : '/admin/lms/courses';
@endphp

@section('title', $isEdit ? 'ویرایش دوره' : 'دورهٔ جدید')

@section('content')
<div class="topline">
    <h1>{{ $isEdit ? '✏️ ' . $course->title() : '📚 دورهٔ جدید' }}</h1>
    <span class="spacer"></span>
    @if($isEdit)
        <a class="btn ghost" href="/lms/courses/{{ $course->slug() }}" target="_blank">👁 ویترین</a>
        <a class="btn ghost" href="/admin/lms/courses/{{ $course->id() }}/quizzes">📝 آزمون‌ها</a>
        @if($course->status() !== \Modules\Lms\Domain\Enums\CourseStatus::Published)
            <form class="inline" method="post" action="/admin/lms/courses/{{ $course->id() }}/publish">
                @csrf
                <button class="btn success" type="submit">انتشار</button>
            </form>
        @else
            <form class="inline" method="post" action="/admin/lms/courses/{{ $course->id() }}/archive">
                @csrf
                <button class="btn ghost" type="submit">بایگانی</button>
            </form>
        @endif
    @endif
</div>

@if($isEdit)
    <div class="stats">
        <div class="stat"><div class="num">{{ trans_number($enrollmentCount ?? 0) }}</div><div class="muted">ثبت‌نام</div></div>
        <div class="stat"><div class="num">{{ trans_number(count($structure)) }}</div><div class="muted">بخش</div></div>
        <div class="stat"><div class="num">{{ trans_number($course->totalDuration()) }}</div><div class="muted">دقیقه محتوا</div></div>
        <div class="stat"><div class="num">{{ $course->status()->labelFa() }}</div><div class="muted">وضعیت</div></div>
    </div>
@endif

<div class="card">
    <form method="post" action="{{ $action }}">
        @csrf
        @if($isEdit)@method('POST')@endif
        <div class="form-grid">
            <label class="full">عنوان دوره *
                <input type="text" name="title" value="{{ $isEdit ? $course->title() : '' }}" required maxlength="255">
            </label>
            <label>slug (اختیاری — خودکار از عنوان)
                <input type="text" name="slug" value="{{ $isEdit ? $course->slug() : '' }}" dir="ltr">
            </label>
            <label>دسته
                <select name="category_id">
                    <option value="">—</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id() }}" {{ $isEdit && $course->categoryId() === $cat->id() ? 'selected' : '' }}>{{ $cat->title() }}</option>
                    @endforeach
                </select>
            </label>
            <label>سطح
                <select name="level">
                    @foreach(\Modules\Lms\Domain\Enums\CourseLevel::cases() as $level)
                        <option value="{{ $level->value }}" {{ $isEdit && $course->level() === $level ? 'selected' : '' }}>{{ $level->labelFa() }}</option>
                    @endforeach
                </select>
            </label>
            <label>زبان
                <input type="text" name="language" value="{{ $isEdit ? $course->language() : 'fa' }}" maxlength="5">
            </label>
            <label>قیمت (ریال — minor-unit)
                <input type="number" name="price" min="0" step="1" value="{{ $isEdit && !$course->isFree() ? $course->price()->minor : 0 }}">
            </label>
            <label style="flex-direction:row;align-items:center;gap:6px">
                <input type="checkbox" name="is_free" value="1" style="width:auto" {{ !$isEdit || $course->isFree() ? 'checked' : '' }}>
                رایگان
            </label>
            <label class="full">خلاصه (markdown)
                <textarea name="summary" rows="2">{{ $isEdit ? strip_tags((string) $course->summary()) : '' }}</textarea>
            </label>
            <label class="full">توضیحات (markdown)
                <textarea name="description">{{ $isEdit ? strip_tags((string) $course->description()) : '' }}</textarea>
            </label>
            <label class="full">پیش‌نیازها ( Ctrl+click — دوره‌های منتشرشده)
                <select name="prerequisites[]" multiple size="5">
                    @foreach($allCourses as $candidate)
                        <option value="{{ $candidate->id() }}"
                            {{ $isEdit && in_array($candidate->id(), $course->prerequisiteIds(), true) ? 'selected' : '' }}>
                            {{ $candidate->title() }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>
        <p style="margin-top:12px">
            <button class="btn" type="submit">{{ $isEdit ? 'ذخیرهٔ تغییرات' : 'ساخت دوره' }}</button>
        </p>
    </form>
</div>

@if($isEdit)
    <div class="card">
        <h3 style="font-size:.95rem;margin-bottom:10px">📁 بخش‌ها و درس‌ها</h3>

        @foreach($structure as $branch)
            @php $section = $branch['section']; @endphp
            <div class="section-box">
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:6px">
                    <form method="post" action="/admin/lms/courses/{{ $course->id() }}/sections/{{ $section->id() }}" style="display:flex;gap:6px;flex:1;flex-wrap:wrap">
                        @csrf
                        <input type="text" name="title" value="{{ $section->title() }}" style="max-width:260px" required>
                        <input type="number" name="position" value="{{ $section->position() }}" min="0" style="max-width:80px" title="ترتیب">
                        <button class="btn sm ghost" type="submit">ذخیرهٔ بخش</button>
                    </form>
                    <form method="post" action="/admin/lms/courses/{{ $course->id() }}/sections/{{ $section->id() }}" onsubmit="return confirm('حذف بخش و درس‌هایش؟')">
                        @csrf
                        @method('DELETE')
                        <button class="btn sm danger" type="submit">حذف بخش</button>
                    </form>
                </div>

                @foreach($branch['lessons'] as $lesson)
                    <div class="lesson-line">
                        <span>{{ $lesson->type()->icon() }}</span>
                        <span class="grow">
                            <strong>{{ $lesson->title() }}</strong>
                            <span class="muted">({{ $lesson->type()->labelFa() }}{{ $lesson->duration() > 0 ? ' — ' . $lesson->duration() . ' دقیقه' : '' }}{{ $lesson->isFreePreview() ? ' — پیش‌نمایش رایگان' : '' }})</span>
                        </span>
                        <details>
                            <summary class="btn sm ghost" style="cursor:pointer">ویرایش</summary>
                            <form method="post" action="/admin/lms/courses/{{ $course->id() }}/lessons/{{ $lesson->id() }}" class="lms-form" style="margin-top:8px;min-width:480px">
                                @csrf
                                <div class="form-grid">
                                    <label>عنوان *<input type="text" name="title" value="{{ $lesson->title() }}" required></label>
                                    <label>نوع
                                        <select name="type">
                                            @foreach(\Modules\Lms\Domain\Enums\LessonType::cases() as $type)
                                                <option value="{{ $type->value }}" {{ $lesson->type() === $type ? 'selected' : '' }}>{{ $type->labelFa() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>slug<input type="text" name="slug" value="{{ $lesson->slug() }}" dir="ltr"></label>
                                    <label>مدت (دقیقه)<input type="number" name="duration" min="0" value="{{ $lesson->duration() }}"></label>
                                    <label class="full">نشانی ویدیو<input type="url" name="video_url" value="{{ $lesson->videoUrl() ?? '' }}" dir="ltr"></label>
                                    <label class="full">مسیر پیوست (storage)<input type="text" name="attachment_path" value="{{ $lesson->attachmentPath() ?? '' }}" dir="ltr"></label>
                                    <label class="full">شناسهٔ آزمون (برای نوع آزمون)<input type="text" name="quiz_id" value="{{ $lesson->quizId() ?? '' }}" dir="ltr"></label>
                                    <label class="full">محتوا (markdown)<textarea name="content">{{ strip_tags((string) $lesson->content()) }}</textarea></label>
                                    <label style="flex-direction:row;align-items:center;gap:6px">
                                        <input type="checkbox" name="is_free_preview" value="1" style="width:auto" {{ $lesson->isFreePreview() ? 'checked' : '' }}>
                                        پیش‌نمایش رایگان
                                    </label>
                                    <label>ترتیب<input type="number" name="position" min="0" value="{{ $lesson->position() }}"></label>
                                </div>
                                <p style="margin-top:8px"><button class="btn sm" type="submit">ذخیرهٔ درس</button></p>
                            </form>
                        </details>
                        <form method="post" action="/admin/lms/courses/{{ $course->id() }}/lessons/{{ $lesson->id() }}" onsubmit="return confirm('حذف درس؟')">
                            @csrf
                            @method('DELETE')
                            <button class="btn sm danger" type="submit">حذف</button>
                        </form>
                    </div>
                @endforeach

                <details style="margin-top:8px">
                    <summary class="btn sm ghost" style="cursor:pointer">+ درس جدید در «{{ $section->title() }}»</summary>
                    <form method="post" action="/admin/lms/courses/{{ $course->id() }}/sections/{{ $section->id() }}/lessons" style="margin-top:8px">
                        @csrf
                        <div class="form-grid">
                            <label>عنوان *<input type="text" name="title" required></label>
                            <label>نوع
                                <select name="type">
                                    @foreach(\Modules\Lms\Domain\Enums\LessonType::cases() as $type)
                                        <option value="{{ $type->value }}">{{ $type->labelFa() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>مدت (دقیقه)<input type="number" name="duration" min="0" value="0"></label>
                            <label>شناسهٔ آزمون<input type="text" name="quiz_id" dir="ltr"></label>
                            <label class="full">نشانی ویدیو<input type="url" name="video_url" dir="ltr"></label>
                            <label class="full">محتوا (markdown)<textarea name="content"></textarea></label>
                            <label style="flex-direction:row;align-items:center;gap:6px">
                                <input type="checkbox" name="is_free_preview" value="1" style="width:auto"> پیش‌نمایش رایگان
                            </label>
                        </div>
                        <p style="margin-top:8px"><button class="btn sm" type="submit">افزودن درس</button></p>
                    </form>
                </details>
            </div>
        @endforeach

        <form method="post" action="/admin/lms/courses/{{ $course->id() }}/sections" style="display:flex;gap:8px;margin-top:8px">
            @csrf
            <input type="text" name="title" placeholder="عنوان بخش جدید" required style="max-width:280px">
            <button class="btn ghost" type="submit">+ بخش</button>
        </form>
    </div>
@endif
@endsection

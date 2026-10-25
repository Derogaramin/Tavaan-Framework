@extends('lms::layouts.admin')

@section('title', 'دوره‌ها')

@section('content')
<div class="topline">
    <h1>📚 دوره‌ها</h1>
    <span class="spacer"></span>
    <a class="btn" href="/admin/lms/courses/create">+ دورهٔ جدید</a>
</div>

<div class="card">
    <form method="get" action="/admin/lms/courses" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <label style="flex:1;min-width:180px;font-size:.85rem">جست‌وجو
            <input type="text" name="q" value="{{ $query }}">
        </label>
        <label style="font-size:.85rem">وضعیت
            <select name="status">
                <option value="">همه</option>
                @foreach(['draft' => 'پیش‌نویس', 'published' => 'منتشرشده', 'archived' => 'آرشیوشده'] as $k => $label)
                    <option value="{{ $k }}" {{ $statusFilter === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn ghost" type="submit">فیلتر</button>
    </form>
</div>

<div class="card">
    <table class="data">
        <thead>
        <tr><th>عنوان</th><th>سطح</th><th>قیمت</th><th>وضعیت</th><th>دانشجو</th><th>عملیات</th></tr>
        </thead>
        <tbody>
        @forelse($courses as $course)
            <tr>
                <td>
                    <a href="/admin/lms/courses/{{ $course->id() }}/edit"><strong>{{ $course->title() }}</strong></a>
                    <br><span class="muted" dir="ltr">/lms/courses/{{ $course->slug() }}</span>
                </td>
                <td>{{ $course->level()->labelFa() }}</td>
                <td>@if($course->isFree())<span class="badge green">رایگان</span>@else{{ trans_number($course->price()->minor) }} ریال@endif</td>
                <td>
                    @if($course->status() === \Modules\Lms\Domain\Enums\CourseStatus::Published)<span class="badge green">منتشرشده</span>
                    @elseif($course->status() === \Modules\Lms\Domain\Enums\CourseStatus::Draft)<span class="badge amber">پیش‌نویس</span>
                    @else<span class="badge">آرشیوشده</span>@endif
                </td>
                <td>{{ trans_number($course->studentCount()) }}</td>
                <td>
                    <a class="btn sm ghost" href="/admin/lms/courses/{{ $course->id() }}/edit">ویرایش</a>
                    <a class="btn sm ghost" href="/admin/lms/courses/{{ $course->id() }}/quizzes">آزمون‌ها</a>
                    @if($course->status() !== \Modules\Lms\Domain\Enums\CourseStatus::Published)
                        <form class="inline" method="post" action="/admin/lms/courses/{{ $course->id() }}/publish">
                            @csrf
                            <button class="btn sm success" type="submit">انتشار</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">دوره‌ای یافت نشد.</td></tr>
        @endforelse
        </tbody>
    </table>

    @php $totalPages = (int) \max(1, \ceil($total / \max(1, $perPage))); @endphp
    @if($totalPages > 1)
        <div class="pagination">
            @for($p = 1; $p <= $totalPages; $p++)
                @if($p === $page)<span class="current">{{ trans_number($p) }}</span>
                @else<a href="/admin/lms/courses?page={{ $p }}&q={{ urlencode($query) }}&status={{ urlencode($statusFilter) }}">{{ trans_number($p) }}</a>@endif
            @endfor
        </div>
    @endif
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">🗂 دسته‌ها</h3>
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
        @foreach($categories as $cat)
            <span class="badge blue">{{ $cat->title() }}
                <form class="inline" method="post" action="/admin/lms/categories/{{ $cat->id() }}" onsubmit="return confirm('حذف دسته؟')">
                    @csrf
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="btn sm danger" style="padding:0 6px" type="submit">×</button>
                </form>
            </span>
        @endforeach
    </div>
    <form method="post" action="/admin/lms/categories" style="display:flex;gap:8px;flex-wrap:wrap">
        @csrf
        <input type="text" name="title" placeholder="عنوان دسته" style="max-width:220px" required>
        <input type="text" name="description" placeholder="توضیح (اختیاری)" style="max-width:280px">
        <button class="btn ghost" type="submit">+ دسته</button>
    </form>
</div>
@endsection

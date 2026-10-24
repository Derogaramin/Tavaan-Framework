@extends('shop::layouts.admin')

@section('title', 'دسته‌ها')

@section('content')
    <div class="topline"><h1>🗂 دسته‌های فروشگاه</h1></div>

    <div class="card">
        <h2 style="font-size:1rem;margin-bottom:10px">+ دستهٔ جدید</h2>
        <form action="/admin/shop/categories" method="POST" class="form-grid">
            @csrf
            <label>عنوان *<input type="text" name="title" required></label>
            <label>والد
                <select name="parent_id">
                    <option value="">— بدون والد —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id() }}">{{ $cat->title() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="full">توضیح<textarea name="description" rows="2"></textarea></label>
            <div class="full"><button class="btn" type="submit">ساخت دسته</button></div>
        </form>
    </div>

    <div class="card" style="padding:0">
        <table class="data">
            <thead><tr><th>شناسه</th><th>عنوان</th><th>slug</th><th>والد</th><th></th></tr></thead>
            <tbody>
            @forelse($categories as $cat)
                <tr>
                    <td>{{ trans_number((int) $cat->id()) }}</td>
                    <td><strong>{{ $cat->parentId() !== null ? '↳ ' : '' }}{{ $cat->title() }}</strong></td>
                    <td class="muted">{{ $cat->slug() }}</td>
                    <td>{{ $cat->parentId() !== null ? trans_number($cat->parentId()) : '—' }}</td>
                    <td>
                        <form action="/admin/shop/categories/{{ $cat->id() }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger sm" type="submit" onclick="return confirm('حذف دسته؟ (فرزندان بدون والد می‌شوند)')">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">دسته‌ای وجود ندارد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection

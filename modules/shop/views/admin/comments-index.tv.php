@extends('shop::layouts.admin')

@section('title', 'نقد و بررسی‌ها')

@section('content')
    <div class="topline">
        <h1>⭐ نقد و بررسی‌ها</h1>
        @if($pendingCount > 0)
            <span class="badge amber" style="align-self:center">{{ trans_number($pendingCount) }} در انتظار</span>
        @endif
    </div>

    <div class="card" style="display:flex;gap:8px">
        @foreach(['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'spam' => 'اسپم', '' => 'همه'] as $val => $label)
            <a class="btn {{ $statusFilter === $val ? '' : 'ghost' }} sm" href="?status={{ $val }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="card" style="padding:0">
        <table class="data">
            <thead><tr><th>نویسنده</th><th>متن</th><th>محصول</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr></thead>
            <tbody>
            @forelse($comments as $comment)
                <tr>
                    <td><strong>{{ $comment->authorName }}</strong><div class="muted">{{ $comment->authorEmail }}</div></td>
                    <td style="max-width:340px">{{ mb_substr($comment->body, 0, 120) }}{{ mb_strlen($comment->body) > 120 ? '…' : '' }}</td>
                    <td class="muted" style="font-size:.75rem">{{ substr($comment->commentableId, 0, 10) }}…</td>
                    <td>
                        <span class="badge {{ $comment->status->value === 'approved' ? 'green' : ($comment->status->value === 'spam' ? 'red' : 'amber') }}">
                            {{ $comment->status->value === 'approved' ? 'تأییدشده' : ($comment->status->value === 'spam' ? 'اسپم' : 'در انتظار') }}
                        </span>
                    </td>
                    <td class="muted">{{ $comment->createdAt !== null ? jalali($comment->createdAt, 'Y/m/d H:i') : '—' }}</td>
                    <td>
                        @if($comment->status->value !== 'approved')
                            <form action="/admin/shop/comments/{{ $comment->id }}/approve" method="POST" class="inline">
                                @csrf
                                <button class="btn sm" type="submit">تأیید</button>
                            </form>
                        @endif
                        @if($comment->status->value !== 'spam')
                            <form action="/admin/shop/comments/{{ $comment->id }}/spam" method="POST" class="inline">
                                @csrf
                                <button class="btn ghost sm" type="submit">اسپم</button>
                            </form>
                        @endif
                        <form action="/admin/shop/comments/{{ $comment->id }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger sm" type="submit">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">نقدی در این وضعیت نیست.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($total > $perPage)
        <?php $pages = (int) ceil($total / $perPage); ?>
        <div class="pagination">
            @for($p = 1; $p <= $pages; $p++)
                @if($p === $page)
                    <span class="current">{{ trans_number($p) }}</span>
                @else
                    <a href="?page={{ $p }}&status={{ urlencode($statusFilter) }}">{{ trans_number($p) }}</a>
                @endif
            @endfor
        </div>
    @endif
@endsection

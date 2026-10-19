@extends('admin::layout')

@section('title', $resource->titleText())

@section('content')
    <h1 class="admin-title">{{ $resource->iconGlyph() }} {{ $resource->titleText() }}</h1>

    <div class="admin-card">
        <form class="toolbar" method="get" action="/{{ $adminPrefix }}/{{ $resource->key() }}">
            <input type="search" name="q" value="{{ $search }}" placeholder="جست‌وجو…">
            @foreach($filters as $column => $value)
                <input type="text" name="{{ $column }}" value="{{ $value }}" placeholder="فیلتر {{ $column }}">
            @endforeach
            <button class="btn" type="submit">اعمال</button>
        </form>

        <div class="toolbar">
            @if($can['create'] ?? false)
                <a class="btn primary" href="/{{ $adminPrefix }}/{{ $resource->key() }}/create">+ ایجاد</a>
            @endif
            @if(count($bulkActions) > 0)
                <form method="post" id="bulk-form" style="display:flex;gap:8px;align-items:center"
                      action="/{{ $adminPrefix }}/{{ $resource->key() }}/actions/{{ $bulkActions[0]->name }}">
                    <input type="hidden" name="_token" value="{{ $token }}">
                    <select name="_bulk" class="btn"
                            onchange="this.form.action='/{{ $adminPrefix }}/{{ $resource->key() }}/actions/'+this.value">
                        @foreach($bulkActions as $bulk)
                            <option value="{{ $bulk->name }}">{{ $bulk->labelText() }}</option>
                        @endforeach
                    </select>
                    <button class="btn" type="submit">اجرا روی انتخاب‌شده‌ها</button>
                </form>
            @endif
        </div>

        <table class="admin-table">
            <thead>
            <tr>
                @if(count($bulkActions) > 0)
                    <th style="width:28px"></th>
                @endif
                @foreach($resource->columnList() as $column)
                    <th>
                        @if(in_array($column, $resource->sortableColumns(), true))
                            <a href="/{{ $adminPrefix }}/{{ $resource->key() }}?sort={{ $column }}&dir={{ $sort === $column && $dir === 'asc' ? 'desc' : 'asc' }}">{{ $column }}</a>
                        @else
                            {{ $column }}
                        @endif
                    </th>
                @endforeach
                <th>عملیات</th>
            </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    @if(count($bulkActions) > 0)
                        <td><input type="checkbox" name="ids[]" value="{{ $row['id'] }}" form="bulk-form"></td>
                    @endif
                    @foreach($resource->columnList() as $column)
                        <td>{{ $row['cells'][$column] ?? '' }}</td>
                    @endforeach
                    <td style="white-space:nowrap">
                        <a class="btn" href="/{{ $adminPrefix }}/{{ $resource->key() }}/{{ $row['id'] }}">نمایش</a>
                        @if($can['update'] ?? false)
                            <a class="btn" href="/{{ $adminPrefix }}/{{ $resource->key() }}/{{ $row['id'] }}/edit">ویرایش</a>
                        @endif
                        @foreach($actions as $action)
                            <form method="post" style="display:inline"
                                  action="/{{ $adminPrefix }}/{{ $resource->key() }}/{{ $row['id'] }}/actions/{{ $action->name }}">
                                <input type="hidden" name="_token" value="{{ $token }}">
                                <button class="btn" type="submit">{{ $action->labelText() }}</button>
                            </form>
                        @endforeach
                        @if($can['delete'] ?? false)
                            <form method="post" style="display:inline"
                                  action="/{{ $adminPrefix }}/{{ $resource->key() }}/{{ $row['id'] }}"
                                  onsubmit="return confirm('حذف شود؟')">
                                <input type="hidden" name="_token" value="{{ $token }}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn danger" type="submit">حذف</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if(count($rows) === 0)
                <tr>
                    <td colspan="{{ count($resource->columnList()) + 2 }}" class="muted" style="text-align:center;padding:24px">
                        رکوردی یافت نشد
                    </td>
                </tr>
            @endif
            </tbody>
        </table>

        @if($paginator->hasPages())
            <div class="pager">
                @for($p = 1; $p <= $paginator->lastPage(); $p++)
                    @if($p === $paginator->currentPage())
                        <span class="current">{{ $p }}</span>
                    @else
                        <a href="/{{ $adminPrefix }}/{{ $resource->key() }}?page={{ $p }}">{{ $p }}</a>
                    @endif
                @endfor
            </div>
        @endif
        <p class="muted">جمع: {{ $paginator->total() }} رکورد</p>
    </div>
@endsection

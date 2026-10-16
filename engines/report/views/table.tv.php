<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>گزارش {{ $title }}</title>
    <style>
        body { font-family: Tahoma, "Segoe UI", sans-serif; margin: 24px; background: #f8fafc; color: #0f172a; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .meta { color: #64748b; font-size: 12px; margin-bottom: 16px; }
        table { border-collapse: collapse; width: 100%; background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; font-size: 13px; text-align: right; }
        th { background: #e8eaf0; font-weight: bold; }
        tr:nth-child(even) td { background: #f8fafc; }
        tfoot td { background: #eef2ff; font-weight: bold; }
        .pivot { margin-top: 24px; }
        .empty { padding: 24px; text-align: center; color: #64748b; }
        .truncated { color: #b45309; font-size: 12px; margin-top: 8px; }
        .num { text-align: left; direction: ltr; }
    </style>
</head>
<body>
    <h1>گزارش {{ $title }}</h1>
    <div class="meta">
        تعداد ردیف: {{ $count }}
        @if ($truncated)
            <span class="truncated">⚠️ نتیجه به‌سبب سقف max_rows بریده شده است</span>
        @endif
    </div>

    @if ($rows === [])
        <div class="empty">داده‌ای برای نمایش وجود ندارد.</div>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell === null ? '—' : $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if ($totals !== [])
                <tfoot>
                    <tr>
                        @foreach ($totalsRow as $cell)
                            <td>{{ $cell === null ? '' : $cell }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif

    @if ($subtotals !== [])
        <div class="pivot">
            <h1>جمع جزء ({{ $groupField }})</h1>
            <table>
                <thead>
                    <tr>
                        <th>{{ $groupField }}</th>
                        @foreach ($subtotalFields as $field)
                            <th>{{ $field }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subtotals as $group => $values)
                        <tr>
                            <td>{{ $group }}</td>
                            @foreach ($values as $value)
                                <td class="num">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($pivot !== null)
        <div class="pivot">
            <h1>جدول متقاطع</h1>
            <table>
                <thead>
                    <tr>
                        <th></th>
                        @foreach ($pivot['columns'] as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pivot['data'] as $rowKey => $cells)
                        <tr>
                            <td>{{ $rowKey }}</td>
                            @foreach ($pivot['columns'] as $column)
                                <td class="num">{{ $cells[$column] === null ? '' : $cells[$column] }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>

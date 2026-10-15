# DOC-053 — موتور Pagination | صفحه‌بندی

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-053 |
| Engine ID | `tavaan.engine.database` (زیرموتور) |
| Namespace | `Tavaan\Engine\Database\Pagination` |
| اولویت | **P0** — فاز M3 |
| وابستگی | `database` |
| LOC | ۳۰۰ |

## ۱. هدف
سه نوع صفحه‌بندی: **Page-based** (سنتی)، **Cursor** (دادهٔ بزرگ)، **Keyset** (بسیار سریع)،
با Meta استاندارد و JsonResource.

## ۲. انواع
| نوع | روش | مزیت | عیب |
|---|---|---|---|
| Page | `LIMIT ? OFFSET ?` + `COUNT(*)` | شمارهٔ صفحه، پرش | کند در صفحات عمیق، COUNT گران |
| Cursor | `WHERE (sort_col, id) > (?, ?)` | سریع، بدون OFFSET | فقط بعدی/قبلی |
| Keyset | `WHERE id > ?` | بسیار سریع | نیاز به مرتب‌سازی پایدار |

## ۳. API
```php
$paginator = DB::table('posts')->orderByDesc('created_at')->paginate(perPage: 20);
$paginator->items();          // list
$paginator->total();          // کل (با COUNT)
$paginator->currentPage();
$paginator->lastPage();
$paginator->hasMorePages();
$paginator->perPage();
$paginator->toArray();        // ['data'=>[], 'meta'=>[...], 'links'=>[...]]
$paginator->toJson();
$paginator->links();          // HTML pagination
$paginator->withQueryString();

// Cursor (بدون COUNT)
$cursor = DB::table('logs')->orderBy('id')->cursorPaginate(perPage: 50);
$cursor->nextCursor(); $cursor->previousCursor();

// Keyset
DB::table('products')->orderBy('id')->keysetPaginate(perPage: 50, afterId: $lastId);
```

## ۴. الگوریتم Cursor Pagination
```php
// به‌جای OFFSET، از آخرین sort key ادامه بده
public function cursorPaginate(int $perPage, ?string $cursor = null): CursorPaginator
{
    $sortColumn = $this->extractSortColumn();       // باید unique/stable باشد
    if ($cursor !== null) {
        $decoded = $this->decodeCursor($cursor);    // base64 → ['created_at'=>..., 'id'=>...]
        $this->where(function ($q) use ($decoded, $sortColumn) {
            // (created_at, id) < (cursor_created_at, cursor_id) برای DESC
            $q->where($sortColumn, '<', $decoded[$sortColumn])
              ->orWhere(fn ($q2) => $q2->where($sortColumn, '=', $decoded[$sortColumn])
                                       ->where('id', '<', $decoded['id']));
        });
    }
    $items = $this->limit($perPage + 1)->get();     // +1 برای تشخیص hasMore
    $hasMore = \count($items) > $perPage;
    if ($hasMore) { \array_pop($items); }
    $nextCursor = $hasMore ? $this->encodeCursor(\end($items)) : null;
    return new CursorPaginator($items, $perPage, $nextCursor, $cursor);
}
```
🔒 **الزام:** ستون مرتب‌سازی باید unique یا همراه با یک ستون unique (مثل id) باشد تا cursor پایدار بماند.

## ۵. Meta و Links (JSON API)
```json
{
  "data": [ /* … */ ],
  "links": {
    "first": "https://api.example.com/posts?page=1",
    "last":  "https://api.example.com/posts?page=10",
    "prev":  null,
    "next":  "https://api.example.com/posts?page=2"
  },
  "meta": {
    "current_page": 1, "from": 1, "last_page": 10,
    "per_page": 20, "to": 20, "total": 200,
    "path": "https://api.example.com/posts"
  }
}
```

## ۶. View Pagination
```blade
{{ $posts->links() }}              {{-- HTML با Tailwind/default --}}
{{ $posts->onEachSide(2)->links() }}
```
🔒 قابل override در `resources/views/vendor/pagination/`.

## ۷. امنیت
| تهدید | کنترل |
|---|---|
| DoS با `?per_page=999999` | سقف `max_per_page` (پیش‌فرض ۱۰۰) |
| Offset منفی | clamp به ۰ |
| page منفی/خیلی بزرگ | clamp |

## ۸. تست
| T-01 | paginate با COUNT درست |
| T-02 | cursor: next/prev بدون OFFSET |
| T-03 | cursor روی صفحات عمیق سریع‌تر از offset |
| T-04 | keyset |
| T-05 | max_per_page clamp |
| T-06 | meta/links JSON |
| T-07 | `withQueryString` |

## ۹. معیار پذیرش 🧪
- AC-PAG-01..07. AC-PAG-08: cursor روی ۱M رکورد ≤ ۵ms (vs offset که > ۱۰۰ms).

## ۱۰. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.pagination.v1
engine:
  id: tavaan.engine.pagination
  namespace: Tavaan\Engine\Database\Pagination
  path: engines/database/src/Pagination
  priority: P0
  phase: M3
  depends_on: [database]
  public_api:
    - class: Paginator
      methods: [items, total, currentPage, lastPage, hasMorePages, toArray, toJson, links, withQueryString]
    - class: CursorPaginator
      methods: [items, nextCursor, previousCursor, hasMorePages, toArray]
  loc_estimate: 300
```

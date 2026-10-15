# DOC-057 — موتور RBAC | نقش و کنترل دسترسی

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-057 |
| Engine ID | `tavaan.engine.rbac` |
| Namespace | `Tavaan\Engine\Rbac` |
| اولویت | **P0** — فاز M4 |
| وابستگی | `auth`, `database`, `cache`, `event` |
| Facade | `Gate` → `gate` |
| LOC | ۱۶۰۰ |
| مرتبط | DOC-100/101/102 (نقش‌ها)، DOC-016 (SEC-AC) |

---

## ۱. هدف
کنترل دسترسی ترکیبی **RBAC + ABAC** با Gate/Policy، سلسله‌مراتب نقش، مجوزهای
خودکار از manifest ماژول، Row-Level Scope و تفویض اختیار.

## ۲. مدل داده
```
┌──────────┐    ┌──────────────┐    ┌──────────────┐
│  users   │───▶│  role_user   │◀───│    roles     │
└──────────┘    └──────────────┘    └──────┬───────┘
                                           │
                                    ┌──────▼────────┐
                                    │permission_role│
                                    └──────┬────────┘
                                           │
                                    ┌──────▼────────┐
                                    │ permissions   │
                                    └───────────────┘
     + groups, group_user, role_hierarchy (parent_id), delegations
```

### ۲٫۱ جداول
```sql
CREATE TABLE tvn_permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module VARCHAR(64) NOT NULL,           -- 'shop'
  `key` VARCHAR(190) NOT NULL UNIQUE,    -- 'shop.order.refund'
  title_fa VARCHAR(190), title_en VARCHAR(190),
  group_id VARCHAR(64),
  created_at DATETIME(3) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE tvn_roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(64) NOT NULL UNIQUE,     -- 'shop-manager'
  name VARCHAR(190) NOT NULL,
  parent_id BIGINT UNSIGNED NULL,        -- سلسله‌مراتب
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  scope VARCHAR(32) NOT NULL DEFAULT 'global',  -- global|tenant|module
  created_at DATETIME(3) NOT NULL,
  FOREIGN KEY (parent_id) REFERENCES tvn_roles(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tvn_role_user (
  role_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NULL,
  expires_at DATETIME(3) NULL,           -- 🔒 نقش موقت
  granted_by BIGINT UNSIGNED NULL,
  PRIMARY KEY (role_id, user_id, tenant_id)
) ENGINE=InnoDB;

CREATE TABLE tvn_permission_role (
  permission_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (permission_id, role_id)
) ENGINE=InnoDB;

CREATE TABLE tvn_delegations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_user_id BIGINT UNSIGNED NOT NULL,
  to_user_id BIGINT UNSIGNED NOT NULL,
  permissions JSON NOT NULL,             -- یا '*'
  starts_at DATETIME(3) NOT NULL,
  ends_at DATETIME(3) NOT NULL,
  reason VARCHAR(255),
  created_at DATETIME(3) NOT NULL
) ENGINE=InnoDB;
```

## ۳. Gate API
```php
// تعریف
Gate::define('shop.order.refund', fn (User $u, Order $o) =>
    $u->hasPermission('shop.order.refund') && $o->isRefundable());
Gate::policy(Order::class, OrderPolicy::class);
Gate::before(fn (User $u, $ability) => $u->isSuperAdmin() ? true : null);
Gate::after(fn (User $u, $ability, $result) => $audit->log(...));

// بررسی
Gate::allows('shop.order.refund', $order);
Gate::denies('shop.order.refund', $order);
Gate::check(['shop.order.view','shop.order.refund'], $order);   // همه
Gate::any([...], $order);                                        // حداقل یکی
Gate::authorize('shop.order.refund', $order);                    // throws 403
Gate::forUser($admin)->allows(...);

// در Controller
$this->authorize('shop.order.refund', $order);
$this->authorizeResource(Order::class, 'order');

// در View
@can('shop.order.refund', $order) ... @endcan
```

## ۴. Policy
```php
final class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->customer_id || $user->can('shop.order.view');
    }
    public function update(User $user, Order $order): bool
    {
        return $user->can('shop.order.update') && !$order->isLocked();
    }
    public function refund(User $user, Order $order): bool
    {
        return $user->can('shop.order.refund')
            && $order->status === OrderStatus::Paid
            && $order->isWithinRefundWindow();
    }
    public function delete(User $user, Order $order): bool { return $user->isSuperAdmin(); }
    public function viewAny(User $user): bool { return $user->can('shop.order.view'); }
}
```
نگاشت متد Policy: `view→viewAny, create→create, update→update, delete→delete, restore, forceDelete`.

## ۵. User API
```php
$user->hasRole('admin');
$user->hasAnyRole(['admin','editor']);
$user->hasPermission('shop.order.refund');       // از نقش‌ها (با سلسله‌مراتب)
$user->permissions();                            // همهٔ مجوزهای مؤثر
$user->roles();
$user->assignRole('editor', tenantId: $tid);
$user->removeRole('editor');
$user->can('shop.order.refund', $order);         // → Gate
$user->cannot(...);
```

## ۶. الگوریتم‌ها

### ۶٫۱ Resolve مجوزهای مؤثر (با سلسله‌مراتب + delegation)
```
effectivePermissions(user, tenant):
  perms = set()
  roles = user.roles(tenant)
  # افزودن نقش‌های والد (سلسله‌مراتب)
  for role in roles:
      r = role
      while r != null:
          perms += r.directPermissions()
          r = r.parent
  # delegationهای فعال
  for d in activeDelegations(to: user):
      perms += d.permissions
  return perms
```
🔒 نتیجه **کش** می‌شود (`tvn:rbac:user:{id}:perms`) و با تغییر نقش invalidate.

### ۶٫۲ Gate::check جریان
```
1. before callbacks → اگر true/false قطعی، برگردان
2. یافتن Policy برای مدل (اگر آرگومان مدل بود)
   → اگر متد policy موجود → فراخوانی
3. وگرنه ability تعریف‌شده (define) → فراخوانی
4. وگرنه → hasPermission(ability)
5. after callbacks
6. return result
```

### ۶٫۳ Row-Level Scope (Ownership)
```php
final class OwnershipScope implements ScopeInterface
{
    public function apply(QueryBuilder $q, Model $m): void
    {
        $user = Auth::user();
        if ($user?->can('*.view-all')) { return; }        // ادمین: همه
        $q->where($m->getOwnerColumn(), $user?->id);      // غیرادمین: فقط مال خود
    }
}
```

## ۷. Sync خودکار مجوزها از manifest
```php
// در boot هر ماژول
final class PermissionSync
{
    public function sync(Module $module): void
    {
        $declared = $module->manifest()->permissions;     // از module.json
        $existing = Permission::where('module', $module->key())->pluck('key')->all();

        $toAdd = \array_diff($declared, $existing);
        $toRemove = \array_diff($existing, $declared);

        Permission::insertMany($toAdd);
        Permission::whereIn('key', $toRemove)->delete();   // 🔒 نرم (حذف از نقش‌ها با event)

        $this->events->dispatch(new PermissionsSynced($module->id(), $toAdd, $toRemove));
    }
}
```
🔒 اجرا در `tvn module:install` و `tvn rbac:sync`.
🔒 مجوزهای حذف‌شده با Audit ثبت می‌شوند.

## ۸. پیکربندی
```php
// config/rbac.php
return [
    'driver' => 'database',                 // database|array
    'tables' => ['permissions'=>'tvn_permissions','roles'=>'tvn_roles', /*…*/],
    'cache' => ['enabled'=>true, 'ttl'=>3600, 'prefix'=>'tvn:rbac:'],
    'hierarchy' => ['enabled'=>true, 'max_depth'=>5],
    'super_admin' => ['role'=>'super-admin', 'bypasses_gate'=>true],
    'wildcard' => ['enabled'=>true, 'separator'=>'*'],   // 'shop.*'
    'delegation' => ['enabled'=>true, 'max_duration_days'=>90],
    'default_role' => env_string('TVN_DEFAULT_ROLE', 'user'),
];
```

## ۹. امنیت
| تهدید | کنترل |
|---|---|
| Privilege Escalation | Deny by default؛ `before` فقط برای super-admin |
| IDOR | Policy + Ownership Scope |
| Role Manipulation | assignRole نیازمند مجوز `rbac.assign` + Audit |
| Cache Poisoning | کلید با tenant + invalidate در تغییر |
| Delegation Abuse | max duration + Audit + revoke |
| Wildcard خطرناک | `*` فقط برای super-admin (پین‌شده) |
| Mass Assignment نقش | `role_id` در guarded |

🔒 `super_admin.bypasses_gate` با احتیاط؛ همیشه Audit.

## ۱۰. کارایی
| عملیات | هدف |
|---|---|
| `Gate::allows` با cache | ≤ ۰٫۰۵ms |
| `hasPermission` (بدون cache) | ≤ ۱ms |
| Resolve همهٔ مجوزها (۵۰ نقش) | ≤ ۵ms |
| Sync ۱۰۰۰ مجوز | ≤ ۲۰۰ms |

## ۱۱. تست
| ID | سناریو |
|---|---|
| T-01 | assignRole + hasRole |
| T-02 | hasPermission از نقش |
| T-03 | سلسله‌مراتب: نقش والد → فرزند ارث می‌برد |
| T-04 | Policy برای مدل |
| T-05 | `before` برای super-admin |
| T-06 | Ownership Scope: کاربر A رکورد B را نمی‌بیند |
| T-07 | Wildcard `shop.*` |
| T-08 | Delegation موقت با انقضا |
| T-09 | Sync از manifest |
| T-10 | Cache invalidate در تغییر نقش |
| T-11 | `authorize` → 403 |

## ۱۲. معیار پذیرش 🧪
- AC-RBAC-01..11.
- AC-RBAC-12: Sync خودکار مجوزها از همهٔ ماژول‌ها.
- AC-RBAC-13: Row-Level Scope نشت = ۰.

## ۱۳. مراجع
| منبع | تفاوت |
|---|---|
| Laravel Gate/Policy | ساختار یکسان | افزودن سلسله‌مراتب، delegation، wildcard |
| spatie/laravel-permission | role/permission | خودنوشته، بدون وابستگی، ABAC |
| Symfony Security Voters | Policy | — |
| CASL (JS) | ABAC rules | — |

## ۱۴. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.rbac.v1
engine:
  id: tavaan.engine.rbac
  namespace: Tavaan\Engine\Rbac
  path: engines/rbac
  priority: P0
  phase: M4
  depends_on: [auth, database, cache, event]
  facade: {name: Gate, accessor: gate}
  tables: [tvn_permissions, tvn_roles, tvn_role_user, tvn_permission_role, tvn_delegations]
  events_emitted: [rbac.permissions.synced, rbac.role.assigned, rbac.role.removed, rbac.delegation.created]
  config_file: config/rbac.php
  permissions_pattern: '<module>.<resource>.<action>'
  public_api:
    - class: Gate
      methods: [define, policy, before, after, allows, denies, check, any, authorize, forUser, abilities]
    - class: RoleManager
      methods: [create, assign, remove, hierarchy, syncFromModules]
  loc_estimate: 1600
  acceptance:
    - {id: AC-RBAC-13, test: 'engines/rbac/tests/Security/RowLevelScopeTest.php'}
```

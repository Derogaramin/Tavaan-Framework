# DOC-075 — موتور FeatureFlag | پرچم قابلیت

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-075 |
| Engine ID | `tavaan.engine.feature-flag` |
| Namespace | `Tavaan\Engine\FeatureFlag` |
| اولویت | **P2** — فاز M6 |
| وابستگی | `cache`, `rbac` |
| Facade | `Feature` |
| LOC | ۶۰۰ |

## ۱. هدف
مدیریت پرچم‌های قابلیت با Targeting (بر اساس نقش/کاربر/درصد/tenant)، Kill Switch،
A/B Testing و انتشار تدریجی — بدون Deploy.

## ۲. API
```php
Feature::enabled('shop.gift-card');
Feature::for($user)->enabled('shop.gift-card');
Feature::value('theme.color', default: 'blue');
Feature::percentage('new-checkout', 25);        // ۲۵٪ کاربران

if (Feature::enabled('shop.gift-card')) { /* … */ }
```

### ۲٫۱ تعریف
```php
// config/features.php یا DB
Feature::define('shop.gift-card', [
    'enabled' => true,
    'rules' => [
        ['type'=>'role', 'value'=>['beta-tester']],
        ['type'=>'user_id', 'value'=>[42, 43]],
        ['type'=>'percentage', 'value'=>25],
        ['type'=>'tenant', 'value'=>['acme','globex']],
    ],
    'default' => false,
]);
```

## ۳. Evaluation
```php
final class FeatureEvaluator
{
    public function evaluate(string $feature, ?User $user = null): bool
    {
        $config = $this->resolve($feature);     // از cache/DB/config
        if ($config === null) { return false; }

        // Kill Switch سراسری
        if ($this->cache->get('feature:kill:' . $feature)) { return false; }

        foreach ($config->rules as $rule) {
            if ($this->matchRule($rule, $user)) { return true; }
        }
        return $config->default;
    }

    private function matchRule(array $rule, ?User $user): bool
    {
        return match ($rule['type']) {
            'role'       => $user !== null && $user->hasAnyRole($rule['value']),
            'permission' => $user !== null && $user->canAny($rule['value']),
            'user_id'    => $user !== null && \in_array($user->id, $rule['value'], true),
            'tenant'     => \in_array(TenantContext::identifier(), $rule['value'], true),
            'percentage' => $this->inPercentage($user, (int) $rule['value']),
            'env'        => \in_array(app()->environment(), $rule['value'], true),
            default      => false,
        };
    }

    /** درصد پایدار بر اساس user id (نه random در هر درخواست) */
    private function inPercentage(?User $user, int $percentage): bool
    {
        $seed = $user?->id ?? $this->request->ip() ?? 'anon';
        $bucket = \crc32((string) $seed) % 100;
        return $bucket < $percentage;
    }
}
```
🔒 `percentage` باید **پایدار** باشد (همان کاربر همیشه در همان bucket) تا تجربهٔ کاربر ثابت بماند.

## ۴. Kill Switch
```php
// غیرفعال‌سازی فوری یک feature در همه‌جا (بدون deploy)
Feature::kill('shop.gift-card');         // → cache flag
Feature::revive('shop.gift-card');
```
🔒 برای مواقع بحرانی (باگ در feature جدید).

## ۵. A/B Testing
```php
$variant = Feature::experiment('checkout-redesign', variants: ['A','B'], user: $user);
// پایدار بر اساس hash(user_id + experiment)
```

## ۶. پیکربندی
```php
// config/features.php
return [
    'driver' => env_string('TVN_FEATURE_DRIVER', 'config'),   // config|database|redis
    'cache_ttl' => 60,
    'features' => [
        'shop.gift-card' => ['enabled'=>false, 'default'=>false, 'rules'=>[]],
        'new.checkout'   => ['enabled'=>true, 'rules'=>[['type'=>'percentage','value'=>10]]],
    ],
];
```

## ۷. امنیت
| تهدید | کنترل |
|---|---|
| نشت feature محرمانه | فقط `enabled()` boolean، نه config |
| تغییر percentage برای سوءاستفاده | نیازمند مجوز `feature.manage` |
| Cache poisoning | کلید با version |

## ۸. تست
| T-01 | enabled/disabled |
| T-02 | role/user/tenant targeting |
| T-03 | percentage پایدار (همان کاربر = همان نتیجه) |
| T-04 | Kill Switch |
| T-05 | A/B variant پایدار |
| T-06 | cache |

## ۹. معیار پذیرش 🧪
- AC-FF-01..06.

## ۱۰. مراجع
| منبع | تفاوت |
|---|---|
| Laravel Pennant | Feature flags | خودنوشته، targeting غنی‌تر |
| Unleash/Flagsmith | Rules | بدون سرویس خارجی |

## ۱۱. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.feature-flag.v1
engine:
  id: tavaan.engine.feature-flag
  namespace: Tavaan\Engine\FeatureFlag
  path: engines/feature-flag
  priority: P2
  phase: M6
  depends_on: [cache, rbac]
  facade: {name: Feature, accessor: feature}
  config_file: config/features.php
  public_api:
    - class: FeatureManager
      methods: [enabled, for, value, define, kill, revive, experiment, percentage]
  loc_estimate: 600
```

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Menu;

use Tavaan\Engine\Rbac\Gate;

/**
 * تَوان | Tavaan — Admin
 * Menu — منوی پویای پیشخوان (DOC-078 §4).
 *
 * منابع: `Admin::menu(fn(Menu $m) => …)` + navigation در manifest ماژول‌ها +
 * resourceهای ثبت‌شده. 🔒 آیتم‌ها هنگام رندر با Gate بر اساس مجوز کاربر
 * فیلتر می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class Menu
{
    /** @var list<MenuItem> */
    private array $items = [];

    /**
     * افزودن آیتم (route = نام روت؛ url = مسیر صریح — یکی کافی است).
     */
    public function add(
        string $label,
        string $route = '',
        string $icon = '',
        int $order = 100,
        ?string $permission = null,
        string $url = '',
    ): self {
        $this->items[] = new MenuItem(
            label: $label,
            url: $url,
            route: $route,
            icon: $icon,
            order: $order,
            permission: $permission,
        );

        return $this;
    }

    /**
     * آیتم‌ها از `module.json → navigation` ماژول‌ها.
     *
     * هر ورودی: `['label' => …, 'route'|'url' => …, 'icon' => …, 'order' => …, 'permission' => …]`
     *
     * @param iterable<object> $modules دارای متد manifest()
     */
    public function addFromManifests(iterable $modules): void
    {
        foreach ($modules as $module) {
            if (!\method_exists($module, 'manifest')) {
                continue;
            }
            $navigation = $module->manifest()->raw['navigation'] ?? [];
            if (!\is_array($navigation)) {
                continue;
            }
            foreach ($navigation as $item) {
                if (!\is_array($item) || !isset($item['label'])) {
                    continue;
                }
                $this->add(
                    label: (string) $item['label'],
                    route: (string) ($item['route'] ?? ''),
                    icon: (string) ($item['icon'] ?? ''),
                    order: (int) ($item['order'] ?? 100),
                    permission: isset($item['permission']) ? (string) $item['permission'] : null,
                    url: (string) ($item['url'] ?? ''),
                );
            }
        }
    }

    /** @return list<MenuItem> همهٔ آیتم‌ها (بدون فیلتر) */
    public function items(): array
    {
        $items = $this->items;
        \usort($items, static fn (MenuItem $a, MenuItem $b): int => [$a->order, $a->label] <=> [$b->order, $b->label]);

        return $items;
    }

    /**
     * آیتم‌های قابل‌دیدن برای کاربر جاری (فیلتر مجوز — §4 🔒).
     *
     * @return list<MenuItem>
     */
    public function visible(Gate $gate): array
    {
        return \array_values(\array_filter(
            $this->items(),
            static fn (MenuItem $item): bool => $item->permission === null || $gate->allows($item->permission),
        ));
    }
}

<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqReadService — مسیر خواندن (درخت عمومی + جست‌وجو + آمار).
 */

namespace Modules\Faq\Application\UseCases;

use Modules\Faq\Domain\Entities\FaqCategory;
use Modules\Faq\Domain\Entities\FaqItem;
use Modules\Faq\Domain\Repositories\FaqCategoryRepositoryInterface;
use Modules\Faq\Domain\Repositories\FaqItemRepositoryInterface;

/**
 * FaqReadService — UseCase خواندن پرسش‌های متداول.
 *
 * درخت عمومی: دسته‌های مرتب + پرسش‌های منتشرشده گروه‌بندی‌شده؛
 * پرسش بدون دسته (یا با دستهٔ trash‌شده) در گروه «بدون دسته».
 * جست‌وجو: mb_stripos روی پرسش + متن پاسخ (بدون تگ).
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
final class FaqReadService
{
    /** @param array<string, mixed> $config کلید config('faq') */
    public function __construct(
        private readonly FaqCategoryRepositoryInterface $categories,
        private readonly FaqItemRepositoryInterface $items,
        private readonly array $config = [],
    ) {
    }

    /**
     * درخت عمومی با جست‌وجو و فیلتر دستهٔ اختیاری.
     *
     * @return array{
     *     categories: list<FaqCategory>,
     *     groups: array<string, list<FaqItem>>,
     *     uncategorized: list<FaqItem>,
     *     q: string,
     *     cat: ?FaqCategory,
     *     total: int
     * }
     */
    public function publicTree(?string $q = null, ?string $categorySlug = null): array
    {
        $categories = $this->categories->all();
        $items = $this->items->publishedAll();

        $cat = null;
        if ($categorySlug !== null && $categorySlug !== '') {
            foreach ($categories as $category) {
                if ($category->slug() === $categorySlug) {
                    $cat = $category;
                    break;
                }
            }
        }

        $minLength = (int) ($this->config['search']['min_length'] ?? 2);
        $needle = $q !== null ? \trim($q) : '';
        if (\mb_strlen($needle) < $minLength) {
            $needle = '';
        }

        $knownIds = [];
        foreach ($categories as $category) {
            $knownIds[$category->id()] = true;
        }

        /** @var array<string, list<FaqItem>> $groups */
        $groups = [];
        /** @var list<FaqItem> $uncategorized */
        $uncategorized = [];
        $total = 0;

        foreach ($items as $item) {
            if ($needle !== '' && !$this->matches($item, $needle)) {
                continue;
            }
            if ($cat !== null && $item->categoryId() !== $cat->id()) {
                continue;
            }
            $categoryId = $item->categoryId();
            if ($categoryId !== null && isset($knownIds[$categoryId])) {
                $groups[$categoryId][] = $item;
            } else {
                $uncategorized[] = $item;
            }
            $total++;
        }

        return [
            'categories' => $categories,
            'groups' => $groups,
            'uncategorized' => $uncategorized,
            'q' => $needle,
            'cat' => $cat,
            'total' => $total,
        ];
    }

    public function findItem(string $id): ?FaqItem
    {
        return $this->items->findById($id);
    }

    public function findCategory(string $id): ?FaqCategory
    {
        return $this->categories->findById($id);
    }

    /** @return list<FaqCategory> گزینه‌های دسته برای فرم‌ها/فیلترها */
    public function categoryOptions(): array
    {
        return $this->categories->all();
    }

    /**
     * آمار برای ویجت داشبورد.
     *
     * @return array{published: int, categories: int}
     */
    public function stats(): array
    {
        return [
            'published' => $this->items->countPublished(),
            'categories' => $this->categories->countAll(),
        ];
    }

    private function matches(FaqItem $item, string $needle): bool
    {
        if (\mb_stripos($item->question(), $needle) !== false) {
            return true;
        }

        return \mb_stripos(\strip_tags($item->answer()), $needle) !== false;
    }
}

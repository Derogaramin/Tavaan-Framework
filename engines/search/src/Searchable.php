<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search;

/**
 * تَوان | Tavaan — Search
 * Searchable — trait مدل‌های قابل‌جست‌وجو (DOC-052 §5).
 *
 * ```php
 * final class Product extends Model
 * {
 *     use Searchable;
 *
 *     public function searchIndex(): string { return 'products'; }
 *     public function toSearchableArray(): array
 *     {
 *         return ['title' => $this->title, 'summary' => $this->summary,
 *                 'category_id' => $this->category_id, 'price' => $this->price];
 *     }
 * }
 * ```
 *
 * ایندکس خودکار: SearchServiceProvider به رویدادهای `model.saved` و
 * `model.deleted` گوش می‌دهد و مدل‌های Searchable را ایندکس/حذف می‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
trait Searchable
{
    /** نام ایندکس این مدل (پیش‌فرض: از config بر اساس نام کلاس). */
    public function searchIndex(): string
    {
        $simple = (new \ReflectionClass($this))->getShortName();

        return \mb_strtolower($simple) . 's';
    }

    /** شناسهٔ سند در ایندکس (پیش‌فرض: کلید اصلی). */
    public function searchId(): int|string|null
    {
        return \method_exists($this, 'getKey') ? $this->getKey() : null;
    }

    /**
     * دادهٔ قابل‌ایندکس — به‌طور پیش‌فرض همهٔ attributes.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return \method_exists($this, 'toArray') ? (array) $this->toArray() : \get_object_vars($this);
    }

    /** ایندکس فوری این سند. */
    public function searchable(): void
    {
        $search = SearchResolver::manager();
        if ($search !== null) {
            $search->index($this->searchIndex(), $this);
        }
    }

    /** حذف فوری این سند از ایندکس. */
    public function unsearch(): void
    {
        $search = SearchResolver::manager();
        $id = $this->searchId();
        if ($search !== null && $id !== null) {
            $search->delete($this->searchIndex(), (string) $id);
        }
    }
}

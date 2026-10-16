<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Search;

/**
 * تَوان | Tavaan — Contracts
 * SearchDriverInterface — قرارداد درایورهای جست‌وجو (DOC-052 §2).
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
interface SearchDriverInterface
{
    /** ساخت ایندکس (جدول/فایل/سرویس). */
    public function createIndex(string $index): void;

    /** حذف کامل ایندکس. */
    public function dropIndex(string $index): void;

    public function indexExists(string $index): bool;

    /**
     * ایندکس یک سند.
     *
     * @param array<string, mixed> $data
     */
    public function index(string $index, string $docId, array $data): void;

    /**
     * ایندکس دسته‌ای.
     *
     * @param array<string, array<string, mixed>> $docs docId → data
     */
    public function indexMany(string $index, array $docs): void;

    public function delete(string $index, string $docId): void;

    /**
     * جست‌وجوی Full-Text.
     *
     * @param array<string, mixed> $options fields/filters/facets/sort/page/per_page/highlight
     */
    public function search(string $index, string $query, array $options = []): \Tavaan\Engine\Search\SearchResult;

    /**
     * پیشنهاد واژه (autocomplete/did-you-mean).
     *
     * @return list<string>
     */
    public function suggest(string $index, string $query, int $limit = 5): array;

    /** پاک‌کردن همهٔ اسناد ایندکس (بدون حذف ساختار). */
    public function flush(string $index): void;

    public function count(string $index): int;
}

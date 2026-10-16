<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Pagination;

use Tavaan\Core\Contracts\Database\PaginatorInterface;

/**
 * تَوان | Tavaan — Database
 * Paginator — صفحه‌بندی Page-based با Meta/Links استاندارد (DOC-053).
 *
 * 🔒 per_page با سقف max_per_page محدود (clamp) می‌شود (DoS).
 *
 * @api 1.0
 * @see docs/03-engines/pagination.md
 */
final class Paginator implements PaginatorInterface
{
    /** @var list<mixed> */
    private readonly array $items;

    private readonly int $total;

    private readonly int $perPage;

    private readonly int $currentPage;

    private readonly int $lastPage;

    /** @var array<string, mixed> */
    private array $options;

    /**
     * @param list<mixed>          $items
     * @param array<string, mixed> $options path/query/fragment
     */
    public function __construct(array $items, int $total, int $perPage, int $currentPage, array $options = [])
    {
        $this->items = \array_values($items);
        $this->perPage = \max(1, $perPage);
        $this->total = \max(0, $total);
        $this->lastPage = \max(1, (int) \ceil($this->total / $this->perPage));
        $this->currentPage = \min(\max(1, $currentPage), $this->lastPage);
        $this->options = $options;
    }

    /** گزینه‌های path/query از محیط درخواست فعلی (در CLI خالی). */
    public static function optionsFromGlobals(): array
    {
        $options = ['path' => '', 'query' => [], 'fragment' => ''];

        $uri = $_SERVER['REQUEST_URI'] ?? null;
        if (\is_string($uri) && $uri !== '') {
            $parts = \parse_url($uri);
            $options['path'] = ($parts['scheme'] ?? '') !== '' ? $uri : (string) ($parts['path'] ?? '/');
            $query = [];
            \parse_str((string) ($parts['query'] ?? ''), $query);
            unset($query['page']);
            $options['query'] = $query;
            $options['fragment'] = (string) ($parts['fragment'] ?? '');
        }

        return $options;
    }

    public function withQueryString(): static
    {
        return $this;
    }

    /** @param array<string, mixed> $options */
    public function withOptions(array $options): static
    {
        $this->options = \array_merge($this->options, $options);

        return $this;
    }

    /** @return list<mixed> */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function from(): ?int
    {
        return $this->total === 0 ? null : ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function to(): ?int
    {
        return $this->total === 0 ? null : ($this->currentPage - 1) * $this->perPage + \count($this->items);
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return [
            'current_page' => $this->currentPage,
            'from'         => $this->from(),
            'last_page'    => $this->lastPage,
            'per_page'     => $this->perPage,
            'to'           => $this->to(),
            'total'        => $this->total,
            'path'         => (string) ($this->options['path'] ?? ''),
        ];
    }

    /** @return array{first: string|null, last: string|null, prev: string|null, next: string|null} */
    public function linkUrls(): array
    {
        $path = (string) ($this->options['path'] ?? '');
        if ($path === '') {
            return ['first' => null, 'last' => null, 'prev' => null, 'next' => null];
        }

        return [
            'first' => $this->url(1),
            'last'  => $this->url($this->lastPage),
            'prev'  => $this->onFirstPage() ? null : $this->url($this->currentPage - 1),
            'next'  => $this->hasMorePages() ? $this->url($this->currentPage + 1) : null,
        ];
    }

    private function url(int $page): string
    {
        /** @var array<string, mixed> $query */
        $query = (array) ($this->options['query'] ?? []);
        $query['page'] = (string) $page;

        $base = (string) ($this->options['path'] ?? '');
        $fragment = (string) ($this->options['fragment'] ?? '');

        return $base . '?' . \http_build_query($query) . ($fragment !== '' ? '#' . $fragment : '');
    }

    /** @return array{data: list<mixed>, links: array<string, string|null>, meta: array<string, mixed>} */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->items as $item) {
            $data[] = \is_object($item) && \method_exists($item, 'toArray') ? $item->toArray() : $item;
        }

        return [
            'data'  => $data,
            'links' => $this->linkUrls(),
            'meta'  => $this->meta(),
        ];
    }

    public function toJson(int $flags = 0): string
    {
        return (string) \json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }

    /** لینک‌های HTML ساده (RTL-safe؛ قابل override در resources/views/vendor/pagination). */
    public function links(int $onEachSide = 2): string
    {
        if (!$this->hasPages()) {
            return '';
        }

        $urls = $this->linkUrls();
        $html = '<nav role="navigation" aria-label="pagination" dir="rtl"><ul style="list-style:none;display:flex;gap:4px;padding:0;margin:0">';

        $html .= $this->link($urls['prev'], '«', $this->onFirstPage());

        $start = \max(1, $this->currentPage - $onEachSide);
        $end = \min($this->lastPage, $this->currentPage + $onEachSide);
        for ($page = $start; $page <= $end; $page++) {
            $active = $page === $this->currentPage;
            $html .= '<li>' . ($active
                ? '<span aria-current="page" style="padding:4px 10px;font-weight:bold">' . $page . '</span>'
                : '<a href="' . $this->escape($this->url($page)) . '" style="padding:4px 10px">' . $page . '</a>') . '</li>';
        }

        $html .= $this->link($urls['next'], '»', !$this->hasMorePages());
        $html .= '</ul></nav>';

        return $html;
    }

    private function link(?string $url, string $label, bool $disabled): string
    {
        if ($disabled || $url === null) {
            return '<li><span aria-disabled="true" style="padding:4px 10px;opacity:.4">' . $label . '</span></li>';
        }

        return '<li><a href="' . $this->escape($url) . '" style="padding:4px 10px" rel="' . ($label === '«' ? 'prev' : 'next') . '">' . $label . '</a></li>';
    }

    private function escape(string $value): string
    {
        return \htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function __toString(): string
    {
        return $this->links();
    }
}

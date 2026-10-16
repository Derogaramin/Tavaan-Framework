<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Pagination;

/**
 * تَوان | Tavaan — Database
 * CursorPaginator — صفحه‌بندی Cursor/Keyset (بدون COUNT/OFFSET — DOC-053 §4).
 *
 * 🔒 cursor = base64url(json) از مقادیر ستون‌های مرتب‌سازی (پایدار با ستون یکتا).
 *
 * @api 1.0
 * @see docs/03-engines/pagination.md
 */
final class CursorPaginator implements \Tavaan\Core\Contracts\Support\Arrayable, \Tavaan\Core\Contracts\Support\Jsonable
{
    /** @param list<mixed> $items */
    public function __construct(
        private readonly array $items,
        private readonly int $perPage,
        private readonly ?string $nextCursor = null,
        private readonly ?string $previousCursor = null,
        private readonly string $cursorKey = 'cursor',
    ) {
    }

    /**
     * رمزگشایی cursor.
     *
     * @return array<string, mixed>
     */
    public static function decodeCursor(string $cursor): array
    {
        $json = \base64_decode(\strtr($cursor, '-_', '+/'), true);
        if ($json === false) {
            throw new \InvalidArgumentException('Invalid pagination cursor.');
        }

        $decoded = \json_decode($json, true);
        if (!\is_array($decoded)) {
            throw new \InvalidArgumentException('Invalid pagination cursor.');
        }

        return $decoded;
    }

    /**
     * رمزگذاری cursor از یک سطر.
     *
     * @param array<string, mixed>                       $row
     * @param list<array{column: string, direction: string}> $sorts
     */
    public static function encodeCursor(array $row, array $sorts): string
    {
        $payload = [];
        foreach ($sorts as $sort) {
            $column = $sort['column'];
            // ستون ممکن است با prefix جدول باشد (o.id → id)
            $key = \str_contains($column, '.') ? \substr($column, (int) \strrpos($column, '.') + 1) : $column;
            $payload[$key] = $row[$key] ?? null;
        }

        $json = (string) \json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return \rtrim(\strtr(\base64_encode($json), '+/', '-_'), '=');
    }

    /** @return list<mixed> */
    public function items(): array
    {
        return $this->items;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function previousCursor(): ?string
    {
        return $this->previousCursor;
    }

    public function hasMorePages(): bool
    {
        return $this->nextCursor !== null;
    }

    public function cursorKey(): string
    {
        return $this->cursorKey;
    }

    /** @return array{data: list<mixed>, links: array{prev: string|null, next: string|null}, meta: array<string, mixed>} */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->items as $item) {
            $data[] = \is_object($item) && \method_exists($item, 'toArray') ? $item->toArray() : $item;
        }

        return [
            'data'  => $data,
            'links' => [
                'prev' => $this->previousCursor,
                'next' => $this->nextCursor,
            ],
            'meta'  => [
                'path'      => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                'per_page'  => $this->perPage,
                'next_cursor' => $this->nextCursor,
                'prev_cursor' => $this->previousCursor,
            ],
        ];
    }

    public function toJson(int $flags = 0): string
    {
        return (string) \json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }
}

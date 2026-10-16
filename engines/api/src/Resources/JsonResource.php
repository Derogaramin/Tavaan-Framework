<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Resources;

use Tavaan\Core\Contracts\Database\PaginatorInterface;
use Tavaan\Core\Contracts\Support\Arrayable;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Api
 * JsonResource — پایهٔ Resource/Transformer پاسخ‌های JSON (DOC-044 §2).
 *
 * ```php
 * final class ProductResource extends JsonResource
 * {
 *     public function toArray(Request $request): array
 *     {
 *         return [
 *             'id' => $this->resource->id,
 *             'in_stock' => $this->when($request->attribute('user') !== null, fn () => true),
 *             'category' => new CategoryResource($this->whenLoaded('category')),
 *         ];
 *     }
 * }
 * return ProductResource::make($product);              // → {"data": {...}}
 * return ProductResource::collection($paginator);      // → {"data": [...], "meta": …, "links": …}
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
abstract class JsonResource implements \JsonSerializable
{
    /** نام کلید پیچندهٔ خروجی (null = بدون wrap). */
    public static ?string $wrap = 'data';

    public mixed $resource;

    private ?Request $requestOverride = null;

    public function __construct(mixed $resource)
    {
        $this->resource = $resource;
    }

    public static function make(mixed $resource): static
    {
        return new static($resource);
    }

    /**
     * مجموعه (آرایه/iterator/Paginator) → AnonymousResourceCollection.
     */
    public static function collection(mixed $resource): AnonymousResourceCollection
    {
        return new AnonymousResourceCollection($resource, static::class);
    }

    /**
     * شکل‌دهی خروجی — توسط زیرکلاس پیاده می‌شود.
     *
     * @return array<array-key, mixed>
     */
    abstract public function toArray(Request $request): array;

    /**
     * resolve کامل: toArray + حذف MissingValue + ادغام MergeValue +
     * resolve تودرتوی JsonResource/Arrayableها.
     *
     * @return array<array-key, mixed>
     */
    public function resolve(?Request $request = null): array
    {
        // 🔒 resource خودش MissingValue است (مثلاً new X($this->whenLoaded('rel')))
        if ($this->resource instanceof MissingValue) {
            return [];
        }

        $request ??= $this->resolveRequest();
        $data = $this->toArray($request);

        return $this->filter($data, $request);
    }

    /** پاسخ JSON آمادهٔ ارسال (با wrap). */
    public function response(?Request $request = null): Response
    {
        return Response::json($this->withWrap($this->resolve($request)));
    }

    public function jsonSerialize(): mixed
    {
        if ($this->resource instanceof MissingValue) {
            return null;
        }

        return $this->withWrap($this->resolve());
    }

    /** درخواست جاری (از container) یا یک درخواست تهی به‌عنوان fallback. */
    public function resolveRequest(): Request
    {
        if ($this->requestOverride !== null) {
            return $this->requestOverride;
        }

        $container = \Tavaan\Engine\Api\ApiResolver::container();
        if ($container !== null && $container->bound('request')) {
            try {
                $request = $container->make('request');
            } catch (\Throwable) {
                $request = null;
            }
            if ($request instanceof Request) {
                return $request;
            }
        }

        return Request::create('http://localhost/');
    }

    /** برای تست: تزریق درخواست. */
    public function withRequest(Request $request): static
    {
        $this->requestOverride = $request;

        return $this;
    }

    // ─── متدهای شرطی (DOC-044 §2.1) ──────────────────────────────────────

    /**
     * مقدار فقط وقتی condition برقرار است (وگرنه کلید حذف می‌شود).
     */
    public function when(bool $condition, mixed $value, mixed $default = null): mixed
    {
        if ($condition) {
            return $value instanceof \Closure ? $value() : $value;
        }

        return $default instanceof \Closure ? $default() : ($default ?? MissingValue::instance());
    }

    /**
     * رابطه فقط وقتی eager-load شده باشد.
     */
    public function whenLoaded(string $relation, mixed $value = null, mixed $default = null): mixed
    {
        if (!$this->relationLoaded($relation)) {
            return $default instanceof \Closure ? $default() : ($default ?? MissingValue::instance());
        }

        if ($value !== null) {
            return $value instanceof \Closure ? $value() : $value;
        }

        return $this->resource->getRelation($relation);
    }

    /** رابطه بارگذاری‌شده و غیرخالی. */
    public function whenHas(string $relation, mixed $value = null, mixed $default = null): mixed
    {
        if (!$this->relationLoaded($relation)) {
            return $default instanceof \Closure ? $default() : ($default ?? MissingValue::instance());
        }

        $rel = $this->resource->getRelation($relation);
        $has = \is_array($rel) || $rel instanceof \Countable
            ? \count($rel) > 0
            : ($rel instanceof \IteratorAggregate ? \iterator_count($rel) > 0 : $rel !== null);

        if (!$has) {
            return $default instanceof \Closure ? $default() : ($default ?? MissingValue::instance());
        }

        return $value instanceof \Closure ? $value() : ($value ?? $rel);
    }

    /** شمارش رابطه (withCount) — کلید `{relation}_count`. */
    public function whenCounted(string $relation, mixed $value = null, mixed $default = null): mixed
    {
        $attribute = $relation . '_count';
        if (!\is_object($this->resource) || !isset($this->resource->{$attribute})) {
            return $default instanceof \Closure ? $default() : ($default ?? MissingValue::instance());
        }

        return $value ?? $this->resource->{$attribute};
    }

    /**
     * ادغام شرطی چند کلید در سطح والد.
     *
     * @param array<array-key, mixed>|\Closure(): array<array-key, mixed> $value
     */
    public function mergeWhen(bool $condition, array|\Closure $value): MergeValue|MissingValue
    {
        if (!$condition) {
            return MissingValue::instance();
        }

        return new MergeValue($value instanceof \Closure ? $value() : $value);
    }

    /** فیلدهای pivot فقط وقتی رابطهٔ pivot بارگذاری شده باشد. */
    public function whenPivotLoaded(string $table, mixed $value = null, mixed $default = null): mixed
    {
        $resource = $this->resource;
        $pivot = \is_object($resource) ? ($resource->pivot ?? null) : null;
        $loaded = \is_object($pivot)
            && (!isset($pivot->pivotTable) || (string) $pivot->pivotTable === $table
                || (\method_exists($pivot, 'getTable') && (string) $pivot->getTable() === $table));

        if (!$loaded) {
            return $default instanceof \Closure ? $default() : ($default ?? MissingValue::instance());
        }

        return $value instanceof \Closure ? $value() : ($value ?? $pivot);
    }

    // ─── درونی ───────────────────────────────────────────────────────────

    private function relationLoaded(string $relation): bool
    {
        $resource = $this->resource;
        if (!\is_object($resource)) {
            return false;
        }
        if (\method_exists($resource, 'relationLoaded')) {
            return (bool) $resource->relationLoaded($relation);
        }

        return isset($resource->{$relation});
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    protected function filter(array $data, Request $request): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $resolved = $this->resolveValue($value, $request);

            if ($resolved instanceof MissingValue) {
                continue;
            }
            if ($resolved instanceof MergeValue) {
                foreach ($this->filter($resolved->data, $request) as $mk => $mv) {
                    $result[$mk] = $mv;
                }
                continue;
            }
            $result[$key] = $resolved;
        }

        return $result;
    }

    private function resolveValue(mixed $value, Request $request): mixed
    {
        if ($value instanceof MissingValue || $value instanceof MergeValue) {
            return $value;
        }
        if ($value instanceof self) {
            if ($value->resource instanceof MissingValue) {
                return MissingValue::instance();
            }

            return $value->resolve($request);
        }
        if ($value instanceof \Closure) {
            return $this->resolveValue($value(), $request);
        }
        if (\is_array($value)) {
            return $this->filter($value, $request);
        }
        if ($value instanceof PaginatorInterface) {
            return $value->toArray();
        }
        if ($value instanceof Arrayable) {
            return $this->resolveValue($value->toArray(), $request);
        }
        if ($value instanceof \JsonSerializable) {
            return $this->resolveValue($value->jsonSerialize(), $request);
        }
        if (\is_object($value) && \method_exists($value, 'toArray')) {
            return $this->resolveValue($value->toArray(), $request);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    protected function withWrap(array $data): array
    {
        $wrap = static::$wrap;
        if ($wrap === null || $wrap === '') {
            return $data;
        }

        return [$wrap => $data];
    }
}

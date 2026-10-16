<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Tests\Fixtures;

use Tavaan\Engine\Api\Attributes\Api;
use Tavaan\Engine\Api\Attributes\ApiParam;
use Tavaan\Engine\Api\Attributes\ApiResponse;
use Tavaan\Engine\Api\Resources\JsonResource;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Validation\FormRequest;

/**
 * Fixtureهای تست موتور API (DOC-044 §10).
 *
 * @api internal
 */

/**
 * مدل ساختگی با روابط قابل‌کنترل (بدون DB).
 */
final class FakeProduct
{
    /** @var array<string, mixed> */
    private array $relations = [];

    /** @param array<string, mixed> $attributes */
    public function __construct(private array $attributes = [])
    {
    }

    public function __get(string $key): mixed
    {
        if (\array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        return $this->attributes[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]) || isset($this->relations[$key]);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function setRelationValue(string $name, mixed $value): void
    {
        $this->relations[$name] = $value;
    }

    public function relationLoaded(string $name): bool
    {
        return \array_key_exists($name, $this->relations);
    }

    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    public function getKey(): mixed
    {
        return $this->attributes['id'] ?? null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }
}

final class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
        ];
    }
}

final class ProductResource extends JsonResource
{
    public static int $lazyProbeCalls = 0;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'in_stock' => $this->when(
                $request->attribute('user') !== null,
                fn (): bool => $this->resource->stock > 0,
            ),
            'lazy' => $this->when(false, static function (): string {
                self::$lazyProbeCalls++;

                return 'never';
            }),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'has_tags' => $this->whenHas('tags', true),
            'comments_count' => $this->whenCounted('comments'),
            ...$this->mergeWhen(($this->resource->price ?? 0) > 0, [
                'price' => $this->resource->price,
                'currency' => 'IRT',
            ]),
            'internal' => $this->when(false, ['secret' => true]),
        ];
    }
}

final class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'price' => ['required', 'numeric'],
            'active' => ['boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

final class TestProductController
{
    #[Api(tags: ['products'], summary: 'فهرست محصولات')]
    #[ApiResponse(status: 200, description: 'موفق')]
    #[ApiResponse(status: 422, description: 'خطای اعتبارسنجی')]
    #[ApiParam(name: 'page', in: 'query', type: 'integer', description: 'شمارهٔ صفحه')]
    public function index(): array
    {
        return [];
    }

    #[Api(tags: ['products'], summary: 'ایجاد محصول')]
    #[ApiResponse(status: 201, description: 'ساخته شد')]
    public function store(StoreProductRequest $request): array
    {
        return [];
    }

    #[Api(tags: ['products'], summary: 'جزئیات محصول')]
    #[ApiResponse(status: 200, description: 'موفق')]
    #[ApiResponse(status: 404, description: 'یافت نشد')]
    public function show(int $product): array
    {
        return [];
    }
}

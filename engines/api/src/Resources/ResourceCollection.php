<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Resources;

use Tavaan\Core\Contracts\Database\PaginatorInterface;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Api
 * ResourceCollection — مجموعه‌ای از Resourceها (DOC-044 §2).
 *
 * وقتی زیرکلاس `collects` داشته باشد، هر عضو به آن کلاس نگاشت می‌شود؛
 * در غیر این صورت اعضا باید خودشان JsonResource باشند.
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
class ResourceCollection extends JsonResource
{
    /** @var class-string<JsonResource>|'' */
    public string $collects = '';

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(Request $request): array
    {
        return \array_map(
            fn (mixed $item): mixed => $this->mapItem($item, $request),
            $this->items(),
        );
    }

    /** @return list<mixed> */
    protected function items(): array
    {
        $resource = $this->resource;

        if ($resource instanceof PaginatorInterface) {
            return $resource->items();
        }
        if ($resource instanceof \Traversable) {
            return \iterator_to_array($resource);
        }
        if (\is_array($resource)) {
            return \array_values($resource);
        }

        return [];
    }

    private function mapItem(mixed $item, Request $request): mixed
    {
        if ($this->collects !== '' && !$item instanceof JsonResource) {
            $class = $this->collects;
            /** @var JsonResource $item */
            $item = new $class($item);
        }

        return $item instanceof JsonResource ? $item->resolve($request) : $item;
    }
}

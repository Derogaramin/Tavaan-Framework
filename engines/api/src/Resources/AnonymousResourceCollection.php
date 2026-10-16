<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Resources;

use Tavaan\Core\Contracts\Database\PaginatorInterface;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Api
 * AnonymousResourceCollection — collection بدون کلاس اختصاصی،
 * با پشتیبانی Paginator (meta/links در سطح بالای پاسخ — DOC-044 §2).
 *
 * خروجی برای Paginator:
 * ```json
 * {"data": [...], "links": {"first":…,"last":…,"prev":…,"next":…},
 *  "meta": {"current_page":1,"from":1,"last_page":2,"per_page":20,"to":20,"total":25}}
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
final class AnonymousResourceCollection extends ResourceCollection
{
    /**
     * @param class-string<JsonResource> $collects
     */
    public function __construct(mixed $resource, string $collects)
    {
        parent::__construct($resource);
        $this->collects = $collects;
    }

    public function response(?Request $request = null): Response
    {
        return Response::json($this->jsonSerialize());
    }

    public function jsonSerialize(): mixed
    {
        $resolved = $this->resolve();

        if ($this->resource instanceof PaginatorInterface) {
            /** @var PaginatorInterface $paginator */
            $paginator = $this->resource;

            return [
                ...$this->withWrap($resolved),
                'links' => $this->paginationLinks($paginator),
                'meta' => $this->paginationMeta($paginator),
            ];
        }

        return $this->withWrap($resolved);
    }

    /** @return array<string, string|null> */
    private function paginationLinks(PaginatorInterface $paginator): array
    {
        if (\method_exists($paginator, 'linkUrls')) {
            $urls = (array) $paginator->linkUrls();

            return [
                'first' => isset($urls['first']) ? (string) $urls['first'] : null,
                'last' => isset($urls['last']) ? (string) $urls['last'] : null,
                'prev' => isset($urls['prev']) ? ($urls['prev'] === null ? null : (string) $urls['prev']) : null,
                'next' => isset($urls['next']) ? ($urls['next'] === null ? null : (string) $urls['next']) : null,
            ];
        }

        return ['first' => null, 'last' => null, 'prev' => null, 'next' => null];
    }

    /** @return array<string, mixed> */
    private function paginationMeta(PaginatorInterface $paginator): array
    {
        if (\method_exists($paginator, 'meta')) {
            return (array) $paginator->meta();
        }

        return [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->from(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->to(),
            'total' => $paginator->total(),
        ];
    }
}

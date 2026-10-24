<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM;

use Tavaan\Core\Contracts\Database\PaginatorInterface;
use Tavaan\Engine\Database\Exceptions\RecordNotFoundException;
use Tavaan\Engine\Database\Pagination\Paginator;
use Tavaan\Engine\Database\QueryBuilder;

/**
 * تَوان | Tavaan — ORM
 * ModelQueryBuilder — Builder آگاه به مدل: hydration، eager loading،
 * scopeها و withCount (DOC-050 §3.1/§4.1).
 *
 * متدهای fluent از راه __call به QueryBuilder داخلی forward می‌شوند
 * (و $this برمی‌گردند)؛ @methodهای زیر همان امضاها را برای تحلیل ایستا
 * و IDE اعلام می‌کنند.
 *
 * @method self select(string ...$columns)
 * @method self distinct()
 * @method self join(string $table, \Closure|string $first, ?string $op = null, ?string $second = null, string $type = 'inner')
 * @method self leftJoin(string $table, \Closure|string $first, ?string $op = null, ?string $second = null)
 * @method self where(string|\Closure|QueryBuilder $column, mixed $op = null, mixed $value = null, string $boolean = 'and')
 * @method self orWhere(string|\Closure|QueryBuilder $column, mixed $op = null, mixed $value = null)
 * @method self whereIn(string $column, array|\Closure|QueryBuilder $values, string $boolean = 'and', bool $not = false)
 * @method self whereNotIn(string $column, array|\Closure|QueryBuilder $values, string $boolean = 'and')
 * @method self whereNull(string $column, string $boolean = 'and', bool $not = false)
 * @method self whereNotNull(string $column, string $boolean = 'and')
 * @method self whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false)
 * @method self whereRaw(string $sql, array $bindings = [], string $boolean = 'and')
 * @method self groupBy(string ...$columns)
 * @method self having(string $column, mixed $op = null, mixed $value = null, string $boolean = 'and')
 * @method self orderBy(string $column, string $direction = 'asc')
 * @method self orderByDesc(string $column)
 * @method self limit(int $limit)
 * @method self offset(int $offset)
 * @method self forUpdate()
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class ModelQueryBuilder
{
    /** @var array<string, array{0: \Closure|null}> نام رابطه → constraint */
    private array $eagerLoad = [];

    /** @var array<string, array{0: string, 1: \Closure|null}> alias → [relation, constraint] */
    private array $withCounts = [];

    private bool $removedSoftDeleteScope = false;

    private bool $onlyTrashed = false;

    public function __construct(
        private readonly Model $model,
        private QueryBuilder $query,
    ) {
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    // ─── eager ────────────────────────────────────────────────────────────

    /**
     * @param string|list<string|array<string, \Closure>> $relations
     */
    public function with(string|array $relations): self
    {
        foreach (\is_string($relations) ? [$relations] : $relations as $key => $value) {
            if (\is_int($key)) {
                $this->eagerLoad[$value] = [null];
            } else {
                $this->eagerLoad[$key] = [$value instanceof \Closure ? $value : null];
            }
        }

        return $this;
    }

    /**
     * @param string|list<string|array<string, \Closure>> $relations
     */
    public function withCount(string|array $relations): self
    {
        foreach (\is_string($relations) ? [$relations] : $relations as $key => $value) {
            if (\is_int($key)) {
                // 🔧 ممیزی M0–M8: فرم لیستی `'posts as alias'` هم پارس شود
                // (پیش‌تر alias به relation name آلوده و بی‌اثر می‌شد).
                $spec = (string) $value;
                if (\str_contains($spec, ' as ')) {
                    [$relationName, $alias] = \explode(' as ', $spec, 2);
                    $this->withCounts[\trim($alias)] = [\trim($relationName), null];
                } else {
                    $this->withCounts[$spec . '_count'] = [$spec, null];
                }
            } else {
                $this->withCounts[\str_contains($key, ' as ') ? \substr($key, (int) \strpos($key, ' as ') + 4) : $key . '_count'] = [
                    \str_contains($key, ' as ') ? \substr($key, 0, (int) \strpos($key, ' as ')) : $key,
                    $value instanceof \Closure ? $value : null,
                ];
            }
        }

        return $this;
    }

    public function withoutTrashed(): self
    {
        $this->removedSoftDeleteScope = true;

        return $this;
    }

    public function withTrashed(): self
    {
        $this->removedSoftDeleteScope = true;

        return $this;
    }

    public function onlyTrashed(): self
    {
        $this->removedSoftDeleteScope = true;
        $this->onlyTrashed = true;

        return $this;
    }

    // ─── اجرا ─────────────────────────────────────────────────────────────

    /** @return list<Model> */
    public function get(): array
    {
        $this->applySoftDeleteScope();
        $this->applyWithCountSubQueries();

        $rows = $this->query->get();

        return $this->hydrateAndEagerLoad($rows);
    }

    public function first(): ?Model
    {
        $models = $this->limit(1)->get();

        return $models[0] ?? null;
    }

    public function firstOrFail(): Model
    {
        $model = $this->first();
        if ($model === null) {
            throw new RecordNotFoundException(context: ['model' => $this->model::class]);
        }

        return $model;
    }

    public function find(int|string $id): ?Model
    {
        return $this->where($this->model->getKeyName(), $id)->first();
    }

    public function findOrFail(int|string $id): Model
    {
        $model = $this->find($id);
        if ($model === null) {
            throw new RecordNotFoundException(context: ['model' => $this->model::class, 'id' => $id]);
        }

        return $model;
    }

    /** @param list<int|string> $ids @return list<Model> */
    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->whereIn($this->model->getKeyName(), $ids)->get();
    }

    public function count(string $column = '*'): int
    {
        $this->applySoftDeleteScope();

        return $this->query->count($column);
    }

    public function sum(string $column): int|float|null
    {
        $this->applySoftDeleteScope();

        return $this->query->sum($column);
    }

    public function avg(string $column): int|float|null
    {
        $this->applySoftDeleteScope();

        return $this->query->avg($column);
    }

    public function min(string $column): int|float|null
    {
        $this->applySoftDeleteScope();

        return $this->query->min($column);
    }

    public function max(string $column): int|float|null
    {
        $this->applySoftDeleteScope();

        return $this->query->max($column);
    }

    public function exists(): bool
    {
        $this->applySoftDeleteScope();

        return $this->query->exists();
    }

    public function value(string $column): mixed
    {
        $this->applySoftDeleteScope();

        return $this->query->value($column);
    }

    /** @return array<int|string, mixed> */
    public function pluck(string $column, ?string $key = null): array
    {
        $this->applySoftDeleteScope();

        return $this->query->pluck($column, $key);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Model
    {
        $model = $this->model->newInstance();
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        $query = clone $this;
        foreach ($attributes as $column => $value) {
            $query->where((string) $column, $value);
        }

        $found = $query->first();
        if ($found !== null) {
            return $found;
        }

        return $this->create([...$attributes, ...$values]);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $query = clone $this;
        foreach ($attributes as $column => $value) {
            $query->where((string) $column, $value);
        }

        $found = $query->first();
        if ($found !== null) {
            $found->fill($values);
            $found->save();

            return $found;
        }

        return $this->create([...$attributes, ...$values]);
    }

    public function paginate(int $perPage = 15, int $page = 1): PaginatorInterface
    {
        $this->applySoftDeleteScope();

        $maxPerPage = 100;
        $perPage = \max(1, \min($perPage, $maxPerPage));
        $page = \max(1, $page);

        $countQuery = clone $this->query;
        $total = $countQuery->count();

        $rows = $this->query->forPage($page, $perPage)->get();
        $items = $this->hydrateAndEagerLoad($rows);

        return new Paginator($items, $total, $perPage, $page, Paginator::optionsFromGlobals());
    }

    /** @param callable(list<Model>): bool|void $callback */
    public function chunk(int $size, callable $callback): bool
    {
        $this->applySoftDeleteScope();

        return $this->query->orderBy($this->model->getKeyName())->chunk($size, function (array $rows) use ($callback): bool {
            return $callback($this->hydrateAndEagerLoad($rows)) !== false;
        });
    }

    /** @return \Generator<int, Model> */
    public function lazy(int $chunkSize = 1000): \Generator
    {
        $this->applySoftDeleteScope();

        foreach ($this->query->lazyById($chunkSize, $this->model->getKeyName()) as $row) {
            yield $this->model->newFromRow($row);
        }
    }

    /**
     * حذف دسته‌جمعی (با رعایت soft delete).
     */
    public function delete(): int
    {
        $this->applySoftDeleteScope();

        if ($this->model->usesSoftDeletes() && !$this->removedSoftDeleteScope) {
            $now = \Tavaan\Support\Time\Clock::utcNow()->format('Y-m-d H:i:s.u');

            return $this->query->update([$this->model->getDeletedAtColumn() => $now]);
        }

        return $this->query->delete();
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        $this->applySoftDeleteScope();

        if ($this->model->hasTimestamps() && !isset($values['updated_at'])) {
            $values['updated_at'] = \Tavaan\Support\Time\Clock::utcNow()->format('Y-m-d H:i:s.u');
        }

        return $this->query->update($values);
    }

    public function clone(): self
    {
        return new self($this->model->newInstance(), $this->query->clone());
    }

    public function __clone(): void
    {
        // query مستقل برای clone
        $this->query = $this->query->clone();
    }

    // ─── forwarding ───────────────────────────────────────────────────────

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        // local scope: scopeActive → ->active()
        $scopeMethod = 'scope' . \ucfirst($method);
        if (\method_exists($this->model, $scopeMethod)) {
            $result = $this->model->{$scopeMethod}($this, ...$arguments);

            return $result instanceof self ? $result : $this;
        }

        $result = $this->query->{$method}(...$arguments);

        return $result instanceof QueryBuilder ? $this : $result;
    }

    // ─── درونی ────────────────────────────────────────────────────────────

    private function applySoftDeleteScope(): void
    {
        if (!$this->model->usesSoftDeletes() || $this->removedSoftDeleteScope) {
            return;
        }

        $this->removedSoftDeleteScope = true;   // یک‌بار

        if ($this->onlyTrashed) {
            $this->query->whereNotNull($this->model->getDeletedAtColumn());

            return;
        }

        $this->query->whereNull($this->model->getDeletedAtColumn());
    }

    private function applyWithCountSubQueries(): void
    {
        foreach ($this->withCounts as $alias => [$relationName]) {
            // 🔒 ممیزی M0–M8 (security:scan): alias در SQL درون‌یابی می‌شود و
            // binding‌پذیر نیست — شناسه باید کاملاً محافظه‌کارانه باشد.
            if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $alias) !== 1) {
                throw new \Tavaan\Engine\Database\Exceptions\UnsafeQueryException(
                    ['alias' => (string) $alias, 'where' => 'withCount'],
                    'alias در withCount فقط می‌تواند شناسهٔ [A-Za-z_][A-Za-z0-9_]* باشد.',
                );
            }
            $relation = $this->model->newRelation((string) $relationName);
            if ($relation === null) {
                continue;
            }

            // زیرکوئری شمارش correlated (کلید والد از کوئری بیرون)
            $correlated = $this->buildCorrelatedCount($relation);
            if ($correlated === null) {
                continue;
            }

            $this->query->selectRaw('(' . $correlated['sql'] . ') as ' . $alias, $correlated['bindings']);
        }
    }

    /**
     * @return array{sql: string, bindings: list<mixed>}|null
     */
    private function buildCorrelatedCount(Relations\Relation $relation): ?array
    {
        $parentTable = $this->model->getTable();
        $parentKey = $this->model->getKeyName();
        $correlatedKey = $parentTable . '.' . $parentKey;

        if ($relation instanceof Relations\HasOneOrMany) {
            $related = $relation->getRelatedClass();
            /** @var Model $relatedModel */
            $relatedModel = new $related();

            return [
                'sql' => 'select count(*) from ' . $relatedModel->getTable()
                    . ' where ' . $relation->foreignKey() . ' = ' . $correlatedKey,
                'bindings' => [],
            ];
        }

        if ($relation instanceof Relations\BelongsToMany) {
            return $relation->buildCountSubQuery($correlatedKey);
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<Model>
     */
    private function hydrateAndEagerLoad(array $rows): array
    {
        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->model->newFromRow($row);
        }

        if ($models === []) {
            return [];
        }

        return $this->eagerLoadRelations($models);
    }

    /**
     * @param list<Model> $models
     *
     * @return list<Model>
     */
    private function eagerLoadRelations(array $models): array
    {
        $loads = $this->eagerLoad;

        // with پیش‌فرض مدل
        foreach ($this->model->defaultWith() as $relation) {
            $loads[$relation] ??= [null];
        }

        foreach ($loads as $name => [$constraint]) {
            // nested: a.b → ابتدا a بارگیری شود سپس b روی نتایج a
            $segments = \explode('.', (string) $name);
            $models = $this->eagerLoadRelation($models, $segments[0], $constraint);

            if (\count($segments) > 1) {
                $nested = \implode('.', \array_slice($segments, 1));
                $children = [];
                foreach ($models as $model) {
                    $related = $model->getRelation($segments[0]);
                    if ($related instanceof Model) {
                        $children[] = $related;
                    } elseif (\is_array($related)) {
                        foreach ($related as $item) {
                            if ($item instanceof Model) {
                                $children[] = $item;
                            }
                        }
                    }
                }
                if ($children !== []) {
                    $this->eagerLoadNested($children, $nested);
                }
            }
        }

        return $models;
    }

    /**
     * @param list<Model> $children
     */
    private function eagerLoadNested(array $children, string $nested): void
    {
        $first = $children[0];
        $segments = \explode('.', $nested);
        $relation = $first->newRelation($segments[0]);
        if ($relation === null) {
            return;
        }
        $relation->setRelationName($segments[0]);

        $relation->getQuery()->resetWheres();
        $relation->addEagerConstraints($children);
        $results = $relation->getEager();
        $relation->match($children, $results);

        if (\count($segments) > 1) {
            $grandChildren = [];
            foreach ($children as $child) {
                $related = $child->getRelation($segments[0]);
                if ($related instanceof Model) {
                    $grandChildren[] = $related;
                } elseif (\is_array($related)) {
                    foreach ($related as $item) {
                        if ($item instanceof Model) {
                            $grandChildren[] = $item;
                        }
                    }
                }
            }
            if ($grandChildren !== []) {
                $this->eagerLoadNested($grandChildren, \implode('.', \array_slice($segments, 1)));
            }
        }
    }

    /**
     * @param list<Model> $models
     *
     * @return list<Model>
     */
    private function eagerLoadRelation(array $models, string $name, ?\Closure $constraint): array
    {
        $relation = $this->model->newRelation($name);
        if ($relation === null) {
            return $models;
        }
        $relation->setRelationName($name);

        $relation->getQuery()->resetWheres();
        $relation->addEagerConstraints($models);

        if ($constraint !== null) {
            $constraint($relation->getQuery());
        }

        $results = $relation->getEager();

        // nested eager روی نتایج (اگر رابطهٔ خودش with پیش‌فرض دارد)
        $relatedModel = $results[0] ?? null;
        if ($relatedModel instanceof Model && $relatedModel->defaultWith() !== []) {
            foreach ($relatedModel->defaultWith() as $nestedName) {
                $nestedRelation = $relatedModel->newRelation($nestedName);
                if ($nestedRelation === null) {
                    continue;
                }
                $nestedRelation->setRelationName($nestedName);
                $nestedRelation->getQuery()->resetWheres();
                $nestedRelation->addEagerConstraints($results);
                $nestedResults = $nestedRelation->getEager();
                $nestedRelation->match($results, $nestedResults);
            }
        }

        return $relation->match($models, $results);
    }
}

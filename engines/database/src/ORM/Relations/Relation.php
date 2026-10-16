<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Relations;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\QueryBuilder;

/**
 * تَوان | Tavaan — ORM
 * Relation — پایهٔ رابطه‌ها (DOC-050 §4).
 *
 * الگوی Eager Loading (§4.1):
 * ```
 * 1. کوئری اصلی → N والد
 * 2. جمع‌آوری کلیدها
 * 3. یک کوئری whereIn برای فرزندان
 * 4. گروه‌بندی در حافظه (dictionary)
 * 5. تطبیق (match) به والدین
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
abstract class Relation
{
    protected QueryBuilder $query;

    /** @var class-string<Model> */
    protected string $relatedClass = Model::class;

    /** @var list<string> ستون‌های pivot قابل خواندن */
    protected array $pivotColumns = [];

    /** نام رابطه (برای setRelation در match) */
    protected string $relationName = '';

    public function __construct(protected readonly Model $parent)
    {
    }

    /**
     * مقداردهی کوئری از مدل مرتبط + اعمال محدودیت lazy.
     *
     * @param class-string<Model> $relatedClass
     */
    protected function bootQuery(string $relatedClass): void
    {
        $this->relatedClass = $relatedClass;
        $related = $this->newRelated();
        $this->query = $related->newModelQuery()->getQuery();
        $this->addConstraints();
    }

    /** @return class-string<Model> */
    public function getRelatedClass(): string
    {
        return $this->relatedClass;
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    protected function newRelated(): Model
    {
        $class = $this->relatedClass;

        return new $class();
    }

    public function setRelationName(string $name): void
    {
        $this->relationName = $name;
    }

    public function relationName(): string
    {
        return $this->relationName;
    }

    /** محدودیت‌های lazy loading (تک والد). */
    abstract public function addConstraints(): void;

    /**
     * محدودیت eager: whereIn روی کلیدهای همهٔ والدین.
     *
     * @param list<Model> $models
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * تطبیق نتایج به والدین (در حافظه — بدون کوئری اضافه).
     *
     * @param list<Model> $models والدین
     * @param list<Model> $results فرزندان
     *
     * @return list<Model> والدین با رابطهٔ پرشده
     */
    abstract public function match(array $models, array $results): array;

    /** نتیجهٔ lazy (یک والد). */
    abstract public function getResults(): mixed;

    /**
     * اجرای کوئری eager (hydrate شده).
     *
     * @return list<Model>
     */
    public function getEager(): array
    {
        $rows = $this->query->get();
        $related = $this->newRelated();

        $models = [];
        foreach ($rows as $row) {
            $models[] = $related->newFromRow($row);
        }

        return $models;
    }

    /**
     * dictionary: گروه‌بندی نتایج بر اساس کلید.
     *
     * @param list<Model> $results
     *
     * @return array<string, list<Model>>
     */
    protected function buildDictionary(array $results, string $key): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $value = $result->getAttribute($key);
            if ($value === null) {
                continue;
            }
            $dictionary[(string) $value][] = $result;
        }

        return $dictionary;
    }

    /**
     * جمع‌آوری کلیدهای والدین (بدون تکرار).
     *
     * @param list<Model> $models
     *
     * @return list<int|string>
     */
    protected function parentKeys(array $models, string $key): array
    {
        $keys = [];
        foreach ($models as $model) {
            $value = $model->getAttribute($key);
            if ($value !== null && !\in_array($value, $keys, true)) {
                $keys[] = $value;
            }
        }

        return $keys;
    }

    // ─── forwarding به QueryBuilder ───────────────────────────────────────

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $result = $this->query->{$method}(...$arguments);

        return $result instanceof QueryBuilder ? $this : $result;
    }

    /** نام ستون خارجی پیش‌فرض: snake(نام کلاس والد)_id */
    public static function defaultForeignKey(Model $parent): string
    {
        $class = $parent::class;
        $short = \substr($class, (int) \strrpos($class, '\\') + 1);

        return \strtolower((string) \preg_replace('/(?<!^)[A-Z]/', '_$0', $short)) . '_id';
    }

    protected function qualify(string $column): string
    {
        return $this->newRelated()->getTable() . '.' . $column;
    }
}

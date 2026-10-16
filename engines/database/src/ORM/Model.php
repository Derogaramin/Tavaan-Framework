<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\ORM\ModelInterface;
use Tavaan\Engine\Database\Connection;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Database\ORM\Casts\CastHandler;
use Tavaan\Engine\Database\ORM\Relations\BelongsTo;
use Tavaan\Engine\Database\ORM\Relations\BelongsToMany;
use Tavaan\Engine\Database\ORM\Relations\HasMany;
use Tavaan\Engine\Database\ORM\Relations\HasManyThrough;
use Tavaan\Engine\Database\ORM\Relations\HasOne;
use Tavaan\Engine\Database\ORM\Relations\HasOneThrough;
use Tavaan\Engine\Database\ORM\Relations\MorphMany;
use Tavaan\Engine\Database\ORM\Relations\MorphOne;
use Tavaan\Engine\Database\ORM\Relations\MorphTo;
use Tavaan\Engine\Database\ORM\Relations\MorphToMany;
use Tavaan\Engine\Database\ORM\Relations\Relation;
use Tavaan\Engine\Database\QueryBuilder;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — ORM
 * Model — پایهٔ Active Record با Cast/Relation/Scope/Observer/SoftDelete (DOC-050).
 *
 * ⚠️ Static State (استثنا بر O-07): resolverهای اتصال/رویداد یک‌بار در boot
 * تنظیم می‌شوند (الگوی استاندارد Entity base — مانند DOC-050 §3).
 *
 * ### مثال
 * ```php
 * final class Product extends Model
 * {
 *     protected string $table = 'shop_products';
 *     protected string $keyType = 'ulid';
 *     protected array $fillable = ['title', 'price'];
 *     protected array $casts = ['price' => 'money:IRR'];
 * }
 * $product = Product::create(['title' => 'x', 'price' => 15000]);
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
abstract class Model implements ModelInterface
{
    // ─── تنظیمات مدل ──────────────────────────────────────────────────────

    protected string $table = '';

    protected string $primaryKey = 'id';

    /** ulid|bigint|uuid */
    protected string $keyType = 'ulid';

    public bool $incrementing = false;

    public bool $timestamps = true;

    public bool $softDeletes = false;

    protected string $deletedAtColumn = 'deleted_at';

    /** @var list<string> */
    protected array $fillable = [];

    /** @var list<string> */
    protected array $guarded = ['*'];

    /** @var list<string> */
    protected array $hidden = [];

    /** @var list<string>|null */
    protected ?array $visible = null;

    /** @var list<string> */
    protected array $appends = [];

    /** @var array<string, string> */
    protected array $casts = [];

    /** @var list<string> eager پیش‌فرض */
    protected array $with = [];

    /** @var list<string> */
    protected array $withCount = [];

    /** @var array<string, class-string> رویداد → کلاس Domain Event */
    protected array $dispatchesEvents = [];

    protected ?string $connectionName = null;

    // ─── state نمونه ──────────────────────────────────────────────────────

    /** @var array<string, mixed> صفات خام (بدون cast) */
    protected array $attributes = [];

    /** @var array<string, mixed> آخرین وضعیت همگام‌شده با DB */
    protected array $original = [];

    /** @var array<string, mixed> تغییرات آخرین save (برای Observerها/audit) */
    protected array $changes = [];

    /** @var array<string, mixed> مقادیر پیشین فیلدهای تغییرکرده (پیش از syncOriginal) */
    protected array $previousOriginal = [];

    /** @var array<string, mixed> کش رابطه‌ها */
    protected array $relations = [];

    public bool $exists = false;

    // ─── static resolverها ────────────────────────────────────────────────

    private static ?ConnectionManager $connectionResolver = null;

    private static ?DispatcherInterface $dispatcher = null;

    private static ?ContainerInterface $container = null;

    /** @var array<class-string, bool> کلاس‌های boot‌شده */
    private static array $booted = [];

    /** @var array<class-string, list<object>> */
    private static array $observers = [];

    /** @var array<class-string, list<\Tavaan\Core\Contracts\ORM\ScopeInterface>> */
    private static array $globalScopes = [];

    private static bool $unguarded = false;

    public static function setConnectionResolver(?ConnectionManager $resolver): void
    {
        self::$connectionResolver = $resolver;
    }

    public static function setEventDispatcher(?DispatcherInterface $dispatcher): void
    {
        self::$dispatcher = $dispatcher;
    }

    public static function setContainer(?ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function unguard(bool $unguarded = true): void
    {
        self::$unguarded = $unguarded;
    }

    // ─── ساخت و boot ──────────────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();

        if ($attributes !== []) {
            $this->fill($attributes);
        }
    }

    private function bootIfNotBooted(): void
    {
        $class = static::class;
        if (isset(self::$booted[$class])) {
            return;
        }
        self::$booted[$class] = true;

        static::boot();
    }

    /** نقطهٔ گسترش مدل (addGlobalScope و …). */
    protected static function boot(): void
    {
    }

    public static function addGlobalScope(\Tavaan\Core\Contracts\ORM\ScopeInterface $scope): void
    {
        self::$globalScopes[static::class][] = $scope;
    }

    /** @param object|class-string $observer */
    public static function observe(object|string $observer): void
    {
        $instance = \is_string($observer)
            ? (self::$container !== null ? self::$container->make($observer) : new $observer())
            : $observer;

        self::$observers[static::class][] = $instance;
    }

    // ─── اتصال ────────────────────────────────────────────────────────────

    public function getConnection(): Connection
    {
        if (self::$connectionResolver === null) {
            throw new \LogicException('Model connection resolver is not set (OrmServiceProvider not booted?).');
        }

        $connection = self::$connectionResolver->connection($this->connectionName);
        if (!$connection instanceof Connection) {
            throw new \LogicException('ORM requires a full Connection.');
        }

        return $connection;
    }

    public function connection(): QueryBuilder
    {
        return $this->getConnection()->query();
    }

    public function getTable(): string
    {
        if ($this->table !== '') {
            return $this->table;
        }

        // پیش‌فرض: snake_case plural ساده از نام کلاس
        $class = static::class;
        $short = \substr($class, (int) \strrpos($class, '\\') + 1);
        $snake = \strtolower((string) \preg_replace('/(?<!^)[A-Z]/', '_$0', $short));

        return $this->table = $snake . 's';
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): int|string|null
    {
        $key = $this->getAttribute($this->primaryKey);

        return $key === null ? null : (\is_int($key) ? $key : (string) $key);
    }

    public function usesSoftDeletes(): bool
    {
        return $this->softDeletes;
    }

    public function getDeletedAtColumn(): string
    {
        return $this->deletedAtColumn;
    }

    public function hasTimestamps(): bool
    {
        return $this->timestamps;
    }

    /** @return list<string> */
    public function defaultWith(): array
    {
        return $this->with;
    }

    // ─── Query ────────────────────────────────────────────────────────────

    public function newModelQuery(): ModelQueryBuilder
    {
        $query = new ModelQueryBuilder($this->newInstance(), $this->getConnection()->query()->table($this->getTable()));

        foreach (self::$globalScopes[static::class] ?? [] as $scope) {
            $scope->apply($query->getQuery(), $this);
        }

        return $query;
    }

    public static function query(): ModelQueryBuilder
    {
        return (new static())->newModelQuery();
    }

    /**
     * forwarding ایستا به Builder (TPost::where(...), ::withTrashed(), ::published() و …).
     *
     * @param list<mixed> $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::query()->{$method}(...$arguments);
    }

    /**
     * @internal فقط برای تست — پاک‌سازی state ایستا (observer/scope/boot).
     */
    public static function flushTestState(): void
    {
        self::$observers = [];
        self::$globalScopes = [];
        self::$booted = [];
    }

    /** @return list<static> */
    public static function all(): array
    {
        /** @var list<static> $models */
        $models = static::query()->get();

        return $models;
    }

    /** @return static|null */
    public static function find(int|string $id): ?self
    {
        return static::query()->find($id);
    }

    public static function findOrFail(int|string $id): self
    {
        return static::query()->findOrFail($id);
    }

    /**
     * @param list<int|string> $ids
     *
     * @return list<static>
     */
    public static function findMany(array $ids): array
    {
        return static::query()->findMany($ids);
    }

    /** @param array<string, mixed> $attributes @return static */
    public static function create(array $attributes): self
    {
        $model = new static();
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     *
     * @return static
     */
    public static function firstOrCreate(array $attributes, array $values = []): self
    {
        return static::query()->firstOrCreate($attributes, $values);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     *
     * @return static
     */
    public static function updateOrCreate(array $attributes, array $values = []): self
    {
        return static::query()->updateOrCreate($attributes, $values);
    }

    // ─── Attributes / Accessors ───────────────────────────────────────────

    public function getAttribute(string $key): mixed
    {
        // رابطهٔ کش‌شده
        if (\array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // صفت خام + cast تنبل
        if (\array_key_exists($key, $this->attributes)) {
            return $this->castAttribute($key, $this->attributes[$key]);
        }

        // accessor: getXAttribute
        $accessor = 'get' . \str_replace(' ', '', \ucwords(\str_replace(['_', '-'], ' ', $key))) . 'Attribute';
        if (\method_exists($this, $accessor)) {
            return $this->{$accessor}();
        }

        // رابطهٔ lazy (متد هم‌نام)
        if (\method_exists($this, $key) && !\method_exists(self::class, $key)) {
            return $this->loadRelationValue($key);
        }

        return null;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        // mutator: setXAttribute
        $mutator = 'set' . \str_replace(' ', '', \ucwords(\str_replace(['_', '-'], ' ', $key))) . 'Attribute';
        if (\method_exists($this, $mutator)) {
            $this->{$mutator}($value);

            return $this;
        }

        $cast = $this->casts[$key] ?? null;
        if ($cast !== null) {
            $value = CastHandler::set($this, $key, $value, $this->attributes, $cast);
        } elseif ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s.u');
        } elseif ($value instanceof \UnitEnum) {
            $value = $value instanceof \BackedEnum ? $value->value : $value->name;
        } elseif (\is_bool($value)) {
            $value = $value ? 1 : 0;
        } elseif (\is_array($value)) {
            $value = (string) \json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    private function castAttribute(string $key, mixed $value): mixed
    {
        $cast = $this->casts[$key] ?? null;
        if ($cast === null) {
            return $value;
        }

        return CastHandler::get($this, $key, $value, $this->attributes, $cast);
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return $this->getAttribute($key) !== null;
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    // ─── fill / mass assignment ───────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $key = (string) $key;

            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
                continue;
            }

            if ($this->isGuarded($key) && $this->strictMassAssignment()) {
                // 🔒 بی‌صدا رد نکن (DOC-050 §9)
                throw new \Tavaan\Engine\Database\ORM\Exceptions\MassAssignmentException(
                    context: ['model' => static::class, 'key' => $key],
                    hint: 'فیلد را به $fillable اضافه یا از $guarded خارج کنید.',
                );
            }
        }

        return $this;
    }

    public function isFillable(string $key): bool
    {
        if (self::$unguarded) {
            return true;
        }

        if ($this->fillable !== []) {
            return \in_array($key, $this->fillable, true);
        }

        return !$this->isGuarded($key);
    }

    public function isGuarded(string $key): bool
    {
        if (self::$unguarded) {
            return false;
        }

        return $this->guarded === ['*'] || \in_array($key, $this->guarded, true);
    }

    private function strictMassAssignment(): bool
    {
        if (self::$container === null || !self::$container->bound('config')) {
            return true;
        }
        $config = self::$container->make('config');

        return (bool) ($config->get('orm.strict_mass_assignment', true));
    }

    /** @param array<string, mixed> $attributes */
    public function forceFill(array $attributes): static
    {
        self::$unguarded = true;
        try {
            return $this->fill($attributes);
        } finally {
            self::$unguarded = false;
        }
    }

    // ─── save / delete ────────────────────────────────────────────────────

    public function save(): bool
    {
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        $saved = $this->exists ? $this->performUpdate() : $this->performInsert();

        if ($saved) {
            $this->fireModelEvent('saved');
        }

        return $saved;
    }

    private function performInsert(): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        $keyName = $this->getKeyName();

        // تولید کلید بر اساس keyType (ADR-0006)
        if (!isset($this->attributes[$keyName]) || $this->attributes[$keyName] === null || $this->attributes[$keyName] === '') {
            $this->attributes[$keyName] = match ($this->keyType) {
                'ulid' => Ulid::generate()->toString(),
                'uuid' => self::uuidV4(),
                default => null,   // auto-increment
            };
            if ($this->attributes[$keyName] === null) {
                unset($this->attributes[$keyName]);
            }
        }

        if ($this->timestamps) {
            $now = Clock::utcNow()->format('Y-m-d H:i:s.u');
            $this->attributes['created_at'] ??= $now;
            $this->attributes['updated_at'] ??= $now;
        }

        $attributes = $this->attributes;
        $autoIncrementId = null;

        if ($this->incrementing && !isset($attributes[$keyName])) {
            $autoIncrementId = $this->getConnection()->table($this->getTable())->insertGetId($attributes);
            $this->attributes[$keyName] = $autoIncrementId;
        } else {
            $this->getConnection()->table($this->getTable())->insert($attributes);
        }

        $this->exists = true;
        $this->changes = $this->castChanges($this->attributes);
        $this->syncOriginal();
        $this->fireModelEvent('created');

        return true;
    }

    private function performUpdate(): bool
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        $dirty = $this->getDirtyForUpdate();
        if ($dirty === []) {
            return true;
        }

        if ($this->timestamps && !isset($dirty['updated_at'])) {
            $dirty['updated_at'] = Clock::utcNow()->format('Y-m-d H:i:s.u');
        }

        $this->getConnection()->table($this->getTable())
            ->where($this->getKeyName(), $this->original[$this->getKeyName()] ?? $this->attributes[$this->getKeyName()] ?? null)
            ->update($dirty);

        foreach ($dirty as $key => $value) {
            $this->attributes[$key] = $value;
        }
        // 🔒 changes و مقادیر پیشین پیش از syncOriginal ثبت شوند (Observer/Audit به diff نیاز دارند)
        $this->changes = $this->castChanges($dirty);
        $this->previousOriginal = \array_intersect_key($this->original, $dirty);
        $this->syncOriginal();
        $this->fireModelEvent('updated');

        return true;
    }

    /** @return array<string, mixed> صفات قابل ذخیره (بدون relation/cast معکوس‌ناپذیر) */
    private function getDirtyForUpdate(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!\array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        if ($this->softDeletes) {
            $now = Clock::utcNow()->format('Y-m-d H:i:s.u');
            $this->attributes[$this->deletedAtColumn] = $now;
            $this->getConnection()->table($this->getTable())
                ->where($this->getKeyName(), $this->getKey())
                ->update([$this->deletedAtColumn => $now]);
            $this->syncOriginal();
        } else {
            $this->performDeleteOnModel();
        }

        $this->fireModelEvent('deleted');

        return true;
    }

    public function forceDelete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $this->performDeleteOnModel();
        $this->fireModelEvent('deleted');

        return true;
    }

    private function performDeleteOnModel(): void
    {
        $this->getConnection()->table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->delete();
        $this->exists = false;
    }

    public function restore(): bool
    {
        if (!$this->softDeletes || !$this->exists) {
            return false;
        }

        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->attributes[$this->deletedAtColumn] = null;
        $this->getConnection()->table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update([$this->deletedAtColumn => null]);
        $this->syncOriginal();

        $this->fireModelEvent('restored');

        return true;
    }

    public function trashed(): bool
    {
        return $this->softDeletes && ($this->attributes[$this->deletedAtColumn] ?? null) !== null;
    }

    /** @param array<string, mixed> $attributes */
    public function update(array $attributes = []): bool
    {
        if (!$this->exists) {
            return false;
        }

        $this->fill($attributes);

        return $this->save();
    }

    public function refresh(): static
    {
        if (!$this->exists) {
            return $this;
        }

        $row = $this->getConnection()->table($this->getTable())->find($this->getKey());
        if ($row !== null) {
            $this->setRawAttributes($row);
        }
        $this->relations = [];

        return $this;
    }

    public function touch(): bool
    {
        if (!$this->timestamps) {
            return false;
        }

        $now = Clock::utcNow()->format('Y-m-d H:i:s.u');
        $this->attributes['updated_at'] = $now;

        if ($this->exists) {
            $this->getConnection()->table($this->getTable())
                ->where($this->getKeyName(), $this->getKey())
                ->update(['updated_at' => $now]);
            $this->syncOriginal();
        }

        return true;
    }

    public function replicate(): static
    {
        $this->fireModelEvent('replicating');

        $clone = $this->newInstance();
        $excluded = [$this->getKeyName(), 'created_at', 'updated_at', $this->deletedAtColumn];
        foreach ($this->attributes as $key => $value) {
            if (!\in_array($key, $excluded, true)) {
                $clone->setAttribute($key, $value);
            }
        }

        return $clone;
    }

    // ─── raw / hydration ──────────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = $attributes;
        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /** @param array<string, mixed> $row */
    public function newFromRow(array $row): static
    {
        $model = $this->newInstance();
        $model->setRawAttributes($row, sync: true);
        $model->exists = true;
        $model->fireModelEvent('retrieved');

        return $model;
    }

    /** @param array<string, mixed> $attributes */
    public function newInstance(array $attributes = []): static
    {
        $model = new static();
        $model->exists = false;
        if ($attributes !== []) {
            $model->forceFill($attributes);
        }

        return $model;
    }

    // ─── dirty tracking ───────────────────────────────────────────────────

    public function isNew(): bool
    {
        return !$this->exists;
    }

    public function isDirty(?string $attribute = null): bool
    {
        if ($attribute !== null) {
            return \array_key_exists($attribute, $this->attributes)
                && (!\array_key_exists($attribute, $this->original) || $this->original[$attribute] !== $this->attributes[$attribute]);
        }

        foreach ($this->attributes as $key => $value) {
            if (!\array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                return true;
            }
        }

        return false;
    }

    public function isClean(?string $attribute = null): bool
    {
        return !$this->isDirty($attribute);
    }

    /**
     * تغییرات آخرین save (پس از syncOriginal هم معتبر است — برای Observer/Audit).
     *
     * @return array<string, mixed>
     */
    public function getChanges(): array
    {
        if ($this->changes !== []) {
            return $this->changes;
        }

        // هنوز save نشده → diff زنده
        $changes = [];
        foreach ($this->attributes as $key => $value) {
            if (!\array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $changes[$key] = $this->castAttribute($key, $value);
            }
        }

        return $changes;
    }

    /**
     * مقادیر «پیشین» فیلدهای تغییرکرده در آخرین update (برای diff ممیزی).
     *
     * @return array<string, mixed>
     */
    public function getPreviousOriginal(): array
    {
        return $this->previousOriginal;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function castChanges(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            $out[$key] = $this->castAttribute((string) $key, $value);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!\array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /** @return array<string, mixed> */
    public function getOriginal(?string $key = null): array
    {
        if ($key !== null) {
            return \array_key_exists($key, $this->original) ? [$key => $this->original[$key]] : [];
        }

        return $this->original;
    }

    public function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    // ─── روابط ────────────────────────────────────────────────────────────

    public function setRelation(string $name, mixed $value): static
    {
        $this->relations[$name] = $value;

        return $this;
    }

    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    public function relationLoaded(string $name): bool
    {
        return \array_key_exists($name, $this->relations);
    }

    public function forgetAttribute(string $name): void
    {
        unset($this->attributes[$name]);
    }

    /**
     * فراخوانی متد رابطه (lazy load در اولین دسترسی).
     */
    private function loadRelationValue(string $name): mixed
    {
        $relation = $this->newRelation($name);
        if ($relation === null) {
            return null;
        }

        $relation->setRelationName($name);
        $result = $relation->getResults();
        $this->relations[$name] = $result;

        return $result;
    }

    /** بارگیری تنبل-عجله‌ای (lazy eager) یک رابطه. */
    public function load(string ...$relations): static
    {
        foreach ($relations as $relation) {
            $rel = $this->newRelation($relation);
            if ($rel === null) {
                continue;
            }
            $rel->setRelationName($relation);
            $this->relations[$relation] = $rel->getResults();
        }

        return $this;
    }

    public function newRelation(string $name): ?Relation
    {
        if (!\method_exists($this, $name) || \method_exists(self::class, $name)) {
            return null;
        }

        $relation = $this->{$name}();

        return $relation instanceof Relation ? $relation : null;
    }

    // ─── کارخانهٔ روابط ──────────────────────────────────────────────────

    /** @param class-string<Model> $related */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        return new HasOne(
            $this,
            $related,
            $foreignKey ?? Relation::defaultForeignKey($this),
            $localKey ?? $this->getKeyName(),
        );
    }

    /** @param class-string<Model> $related */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        return new HasMany(
            $this,
            $related,
            $foreignKey ?? Relation::defaultForeignKey($this),
            $localKey ?? $this->getKeyName(),
        );
    }

    /** @param class-string<Model> $related */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $relatedInstance = new $related();
        $guess = \str_contains($related, '\\')
            ? \strtolower((string) \preg_replace('/(?<!^)[A-Z]/', '_$0', \substr($related, (int) \strrpos($related, '\\') + 1))) . '_id'
            : $related . '_id';

        return new BelongsTo(
            $this,
            $related,
            $foreignKey ?? $guess,
            $ownerKey ?? $relatedInstance->getKeyName(),
        );
    }

    /**
     * @param class-string<Model> $related
     * @param list<string>        $pivotColumns
     */
    protected function belongsToMany(string $related, ?string $pivotTable = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null, ?string $parentKey = null, ?string $relatedKey = null, array $pivotColumns = []): BelongsToMany
    {
        $relatedInstance = new $related();

        $parentBase = $this->snakeClass(static::class);
        $relatedBase = $this->snakeClass($related);

        if ($pivotTable !== null) {
            $table = $pivotTable;
        } else {
            $pair = $parentBase === $relatedBase
                ? [$parentBase, 'self']
                : (\strcmp($parentBase, $relatedBase) < 0 ? [$parentBase, $relatedBase] : [$relatedBase, $parentBase]);
            $table = \implode('_', $pair);
        }

        return new BelongsToMany(
            $this,
            $related,
            $table,
            $foreignPivotKey ?? $parentBase . '_id',
            $relatedPivotKey ?? $relatedBase . '_id',
            $parentKey ?? $this->getKeyName(),
            $relatedKey ?? $relatedInstance->getKeyName(),
            $pivotColumns,
        );
    }

    /**
     * @param class-string<Model> $related
     * @param class-string<Model> $through
     */
    protected function hasManyThrough(string $related, string $through, ?string $firstKey = null, ?string $secondKey = null, ?string $localKey = null): HasManyThrough
    {
        $throughInstance = new $through();

        return new HasManyThrough(
            $this,
            $related,
            $through,
            $firstKey ?? Relation::defaultForeignKey($this),
            $secondKey ?? Relation::defaultForeignKey($throughInstance),
            $localKey ?? $this->getKeyName(),
        );
    }

    /**
     * @param class-string<Model> $related
     * @param class-string<Model> $through
     */
    protected function hasOneThrough(string $related, string $through, ?string $firstKey = null, ?string $secondKey = null, ?string $localKey = null): HasOneThrough
    {
        $throughInstance = new $through();

        return new HasOneThrough(
            $this,
            $related,
            $through,
            $firstKey ?? Relation::defaultForeignKey($this),
            $secondKey ?? Relation::defaultForeignKey($throughInstance),
            $localKey ?? $this->getKeyName(),
        );
    }

    /** @param class-string<Model> $related */
    protected function morphOne(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): MorphOne
    {
        return new MorphOne(
            $this,
            $related,
            $type ?? $name . '_type',
            $id ?? $name . '_id',
            $localKey ?? $this->getKeyName(),
        );
    }

    /** @param class-string<Model> $related */
    protected function morphMany(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): MorphMany
    {
        return new MorphMany(
            $this,
            $related,
            $type ?? $name . '_type',
            $id ?? $name . '_id',
            $localKey ?? $this->getKeyName(),
        );
    }

    protected function morphTo(?string $name = null, ?string $type = null, ?string $id = null): MorphTo
    {
        $name ??= $this->guessMorphName();

        return new MorphTo($this, $type ?? $name . '_type', $id ?? $name . '_id');
    }

    /**
     * @param class-string<Model> $related
     * @param list<string>        $pivotColumns
     */
    protected function morphToMany(string $related, string $name, ?string $pivotTable = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null, ?string $parentKey = null, ?string $relatedKey = null, array $pivotColumns = []): MorphToMany
    {
        $relatedInstance = new $related();
        $relatedBase = $this->snakeClass($related);

        return new MorphToMany(
            $this,
            $related,
            $pivotTable ?? $name . '_' . $relatedBase,
            $foreignPivotKey ?? $name . '_type',
            $relatedPivotKey ?? $name . '_id',
            $relatedBase . '_id',
            $parentKey ?? $this->getKeyName(),
            $relatedKey ?? $relatedInstance->getKeyName(),
            $pivotColumns,
        );
    }

    private function guessMorphName(): string
    {
        $debug = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        foreach ($debug as $frame) {
            if (isset($frame['class'], $frame['function'])
                && \is_subclass_of($frame['class'], self::class)
                && $frame['function'] !== 'morphTo'
                && $frame['function'] !== 'newRelation'
                && $frame['function'] !== '__call') {
                return $frame['function'];
            }
        }

        return 'morphable';
    }

    private function snakeClass(string $class): string
    {
        $short = \substr($class, (int) \strrpos($class, '\\') + 1);

        return \strtolower((string) \preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
    }

    // ─── رویدادها / Observerها ────────────────────────────────────────────

    /**
     * @return mixed false = لغو عملیات
     */
    private function fireModelEvent(string $event): mixed
    {
        // 1) Observerها
        foreach (self::$observers[static::class] ?? [] as $observer) {
            if (\method_exists($observer, $event)) {
                if ($observer->{$event}($this) === false) {
                    return false;
                }
            }
        }

        // 2) dispatchesEvents → Domain Event
        if (isset($this->dispatchesEvents[$event]) && self::$dispatcher !== null) {
            $eventClass = $this->dispatchesEvents[$event];
            self::$dispatcher->dispatch(new $eventClass($this));
        }

        // 3) رویداد رشته‌ای عمومی
        self::$dispatcher?->dispatch('model.' . $event, [
            'model' => static::class,
            'key'   => $this->getKey(),
        ]);

        return null;
    }

    // ─── سریال‌سازی ───────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $array = [];

        foreach ($this->attributes as $key => $value) {
            if ($this->isHidden($key)) {
                continue;
            }
            $array[$key] = $this->serializeValue($this->castAttribute($key, $value));
        }

        foreach ($this->relations as $key => $relation) {
            if ($this->isHidden($key)) {
                continue;
            }
            $array[$key] = $this->serializeValue($relation);
        }

        foreach ($this->appends as $key) {
            $array[$key] = $this->serializeValue($this->getAttribute($key));
        }

        return $array;
    }

    private function isHidden(string $key): bool
    {
        if ($this->visible !== null) {
            return !\in_array($key, $this->visible, true);
        }

        return \in_array($key, $this->hidden, true);
    }

    private function serializeValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof self => $value->toArray(),
            $value instanceof \Tavaan\Engine\Database\ORM\Support\Money => $value->toArray(),
            $value instanceof \Tavaan\Engine\Compat\Decimal => $value->toString(),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \JsonSerializable => $value->jsonSerialize(),
            \is_array($value) => \array_map(fn ($v): mixed => $this->serializeValue($v), $value),
            default => $value,
        };
    }

    public function toJson(int $flags = 0): string
    {
        return (string) \json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    // ─── کمک‌ها ────────────────────────────────────────────────────────────

    private static function uuidV4(): string
    {
        $bytes = \random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);
        $hex = \bin2hex($bytes);

        return \substr($hex, 0, 8) . '-' . \substr($hex, 8, 4) . '-' . \substr($hex, 12, 4)
            . '-' . \substr($hex, 16, 4) . '-' . \substr($hex, 20, 12);
    }

    /** @return array<string, string> */
    public function getCasts(): array
    {
        return $this->casts;
    }
}

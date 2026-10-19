<?php

declare(strict_types=1);

namespace Tavaan\Testing\Database;

use Tavaan\Engine\Database\ORM\Model;

/**
 * تَوان | Tavaan — Testing
 * Factory — پایهٔ Factory مدل‌ها (DOC-084E §6).
 *
 * ```php
 * final class UserFactory extends Factory
 * {
 *     protected string $model = User::class;
 *
 *     public function definition(): array
 *     {
 *         return ['name' => $this->faker->name(), 'email' => $this->faker->unique()->email()];
 *     }
 *
 *     public function admin(): self { return $this->state(['role' => 'admin']); }
 * }
 *
 * User::factory()->count(5)->admin()->create();
 * ```
 *
 * حالت‌ها (state) و شمار (count) با clone اعمال می‌شوند → زنجیره immutable است.
 * `Model::factory()` از طریق resolver که `TestCase` ثبت می‌کند به اینجا می‌رسد
 * (قرارداد نام: `<Model>Factory` — صریح: `public static string $factory`).
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
abstract class Factory
{
    /** @var class-string<Model> */
    protected string $model = '';

    protected Faker $faker;

    protected int $count = 1;

    /** @var list<array<string, mixed>|\Closure(array<string, mixed>): array<string, mixed>> */
    protected array $states = [];

    private static bool $resolverBound = false;

    public function __construct(?Faker $faker = null)
    {
        $this->faker = $faker ?? new Faker();
    }

    /**
     * صفات پیش‌فرض (هر فراخوانی تازه ارزیابی می‌شود → Faker پیش می‌رود).
     *
     * @return array<string, mixed>
     */
    abstract public function definition(): array;

    // ─── resolver مدل‌ها ──────────────────────────────────────────────────

    /**
     * ثبت resolver روی `Model::factory()` (یک‌بار — توسط TestCase صدا زده می‌شود).
     */
    public static function ensureModelResolver(): void
    {
        if (self::$resolverBound) {
            return;
        }
        self::$resolverBound = true;

        if (\method_exists(Model::class, 'setFactoryResolver')) {
            Model::setFactoryResolver(static fn (string $model): Factory => self::for($model));
        }
    }

    /**
     * Factory یک مدل بر اساس قرارداد نام.
     *
     * ترتیب جست‌وجو: `static $factory` صریح روی مدل → `<NS>\<Model>Factory` →
     * `<root>\Database\Factories\<Model>Factory` → `\Database\Factories\<Model>Factory`.
     *
     * @param class-string<Model> $model
     */
    public static function for(string $model): Factory
    {
        if (!\class_exists($model)) {
            throw new \InvalidArgumentException(\sprintf('Factory: model [%s] does not exist.', $model));
        }

        if (\property_exists($model, 'factory')) {
            $explicit = $model::$factory;
            if (\is_string($explicit) && $explicit !== '' && \class_exists($explicit)) {
                return new $explicit();
            }
        }

        $reflection = new \ReflectionClass($model);
        $short = $reflection->getShortName();
        $namespace = $reflection->getNamespaceName();

        $candidates = [$namespace . '\\' . $short . 'Factory'];
        $root = \explode('\\', $namespace)[0] ?? '';
        if ($root !== '') {
            $candidates[] = $root . '\\Database\\Factories\\' . $short . 'Factory';
        }
        $candidates[] = 'Database\\Factories\\' . $short . 'Factory';

        foreach ($candidates as $candidate) {
            if (\class_exists($candidate)) {
                return new $candidate();
            }
        }

        throw new \InvalidArgumentException(\sprintf(
            'Factory: no factory found for model [%s] — tried [%s].',
            $model,
            \implode(', ', $candidates),
        ));
    }

    // ─── Builder روان ─────────────────────────────────────────────────────

    /**
     * افزودن حالت (آرایه یا Closure روی صفات جاری).
     *
     * @param array<string, mixed>|\Closure(array<string, mixed>): array<string, mixed> $state
     */
    public function state(array|\Closure $state): static
    {
        $clone = clone $this;
        $clone->states[] = $state;

        return $clone;
    }

    public function count(int $count): static
    {
        $clone = clone $this;
        $clone->count = \max(1, $count);

        return $clone;
    }

    /** Faker جاری (برای stateهای Closure/سفارشی). */
    public function faker(): Faker
    {
        return $this->faker;
    }

    // ─── ساخت ─────────────────────────────────────────────────────────────

    /**
     * ساخت نمونه‌های **ذخیره‌نشده**.
     *
     * @param array<string, mixed> $overrides
     *
     * @return Model|list<Model>
     */
    public function make(array $overrides = []): Model|array
    {
        $models = [];
        for ($i = 0; $i < $this->count; $i++) {
            $models[] = $this->newModel($overrides);
        }

        return $this->count === 1 ? $models[0] : $models;
    }

    /**
     * ساخت و **ذخیره** در دیتابیس جاری (SQLite in-memory با RefreshDatabase).
     *
     * @param array<string, mixed> $overrides
     *
     * @return Model|list<Model>
     */
    public function create(array $overrides = []): Model|array
    {
        $models = [];
        for ($i = 0; $i < $this->count; $i++) {
            $model = $this->newModel($overrides);
            $model->save();
            $models[] = $model;
        }

        return $this->count === 1 ? $models[0] : $models;
    }

    /** @param array<string, mixed> $overrides */
    private function newModel(array $overrides): Model
    {
        $modelClass = $this->modelClass();

        $attributes = $this->definition();
        foreach ($this->states as $state) {
            $attributes = \array_replace($attributes, $state instanceof \Closure ? $state($attributes) : $state);
        }
        $attributes = \array_replace($attributes, $overrides);

        // mass-assignment آزاد در Factory (مثل Laravel) — فقط حین ساخت
        Model::unguard(true);
        try {
            return new $modelClass($attributes);
        } finally {
            Model::unguard(false);
        }
    }

    /** @return class-string<Model> */
    private function modelClass(): string
    {
        if ($this->model === '' || !\class_exists($this->model)) {
            throw new \LogicException(\sprintf(
                'Factory [%s]: protected string $model must be a valid Model class-string.',
                static::class,
            ));
        }
        if (!\is_subclass_of($this->model, Model::class)) {
            throw new \LogicException(\sprintf(
                'Factory [%s]: model [%s] must extend %s.',
                static::class,
                $this->model,
                Model::class,
            ));
        }

        return $this->model;
    }
}

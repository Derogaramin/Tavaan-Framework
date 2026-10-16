<?php

declare(strict_types=1);

namespace Tavaan\Engine\FeatureFlag;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;

/**
 * تَوان | Tavaan — FeatureFlag
 * FeatureManager — نقطهٔ ورود پرچم‌های قابلیت (Facade: Feature — DOC-075).
 *
 * منابع تعریف (اولویت بالا → پایین):
 * 1. تعریف زمان‌اجرا (`Feature::define`)
 * 2. درایور database (جدول tvn_features با cache)
 * 3. config('features.features.{name}')
 *
 * Kill Switch (cache) روی همهٔ منابع اولویت مطلق دارد.
 *
 * @api 1.0
 * @see docs/03-engines/feature-flag.md
 */
final class FeatureManager
{
    /** @var array<string, array<string, mixed>> تعاریف زمان‌اجرا */
    private array $runtime = [];

    /** @var array<string, mixed>|null */
    private ?array $config = null;

    private string $driver;

    private int $cacheTtl;

    private string $cacheVersion;

    private FeatureEvaluator $evaluator;

    public function __construct(
        private readonly ?CacheRepository $cache = null,
        ?array $config = null,
        ?string $environment = null,
        ?string $requestIp = null,
    ) {
        $this->config = $config;
        $this->driver = (string) ($config['driver'] ?? 'config');
        $this->cacheTtl = \max(0, (int) ($config['cache_ttl'] ?? 60));
        $this->cacheVersion = (string) ($config['version'] ?? 'v1');
        $this->evaluator = new FeatureEvaluator($this->cache, $environment, $requestIp);
    }

    // ─── API عمومی ───────────────────────────────────────────────────────

    public function enabled(string $feature, ?object $user = null): bool
    {
        $definition = $this->resolve($feature);
        if ($definition === null) {
            // kill روی feature تعریف‌نشده هم اثر دارد (موقعیت بحرانی)
            return false;
        }

        return $this->evaluator->evaluate($definition, $feature, $user);
    }

    /** ارزیابی در حوزهٔ یک کاربر مشخص. */
    public function for(?object $user): ScopedFeature
    {
        return new ScopedFeature($this, $user);
    }

    /** مقدار غیر‌بولی یک flag (مثل theme.color). */
    public function value(string $feature, mixed $default = null): mixed
    {
        if ($this->isKilled($feature)) {
            return $default;
        }
        $definition = $this->resolve($feature);
        if ($definition === null || (\array_key_exists('enabled', $definition) && !(bool) $definition['enabled'])) {
            return $default;
        }

        return \array_key_exists('value', $definition) ? $definition['value'] : $default;
    }

    /**
     * تعریف/override یک feature در زمان اجرا.
     *
     * @param array<string, mixed> $definition {enabled?, rules?, default?, value?}
     */
    public function define(string $feature, array $definition): void
    {
        $this->runtime[$feature] = $this->normalize($definition);

        if ($this->driver === 'database' && $this->cache !== null) {
            $this->persist($feature, $this->runtime[$feature]);
            $this->cache->forget($this->cacheKey($feature));
        }
    }

    /** تعریف سریع rule درصدی. */
    public function percentage(string $feature, int $percentage): void
    {
        $existing = $this->runtime[$feature] ?? [];
        $rules = (array) ($existing['rules'] ?? []);
        $rules[] = ['type' => 'percentage', 'value' => \max(0, \min(100, $percentage))];
        $this->define($feature, [...$existing, 'enabled' => true, 'rules' => $rules]);
    }

    /** 🔒 غیرفعال‌سازی فوری و سراسری (بدون deploy). */
    public function kill(string $feature): void
    {
        $this->cache?->forever($this->evaluator->killKey($feature), 1);
    }

    public function revive(string $feature): void
    {
        $this->cache?->forget($this->evaluator->killKey($feature));
    }

    public function isKilled(string $feature): bool
    {
        return $this->cache !== null && $this->cache->get($this->evaluator->killKey($feature)) !== null;
    }

    /**
     * A/B Testing پایدار — انتخاب variant بر اساس hash(seed|experiment).
     *
     * @param list<string> $variants
     */
    public function experiment(string $experiment, array $variants, ?object $user = null): ?string
    {
        $variants = \array_values(\array_filter(\array_map('strval', $variants), static fn (string $v): bool => $v !== ''));
        if ($variants === []) {
            return null;
        }

        $seed = $this->evaluator->seed($user);
        $bucket = \crc32($seed . '|' . $experiment) % 100;
        $index = (int) \floor($bucket / (100 / \count($variants)));
        $index = \min($index, \count($variants) - 1);

        return $variants[$index];
    }

    /** @return list<string> همهٔ featureهای شناخته‌شده (config + runtime + db) */
    public function known(): array
    {
        $names = \array_keys((array) ($this->config()['features'] ?? []));
        foreach (\array_keys($this->runtime) as $name) {
            if (!\in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** پاک‌کردن cache تعاریف (پس از تغییر DB). */
    public function flushCache(?string $feature = null): void
    {
        if ($this->cache === null) {
            return;
        }
        if ($feature !== null) {
            $this->cache->forget($this->cacheKey($feature));

            return;
        }
        foreach ($this->known() as $name) {
            $this->cache->forget($this->cacheKey($name));
        }
    }

    public function evaluator(): FeatureEvaluator
    {
        return $this->evaluator;
    }

    // ─── درونی ───────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private function resolve(string $feature): ?array
    {
        // ۱) runtime
        if (isset($this->runtime[$feature])) {
            return $this->runtime[$feature];
        }

        // ۲) database driver (با cache)
        if ($this->driver === 'database') {
            $fromDb = $this->fromDatabase($feature);
            if ($fromDb !== null) {
                return $fromDb;
            }
        }

        // ۳) config
        $definitions = (array) ($this->config()['features'] ?? []);
        if (isset($definitions[$feature]) && \is_array($definitions[$feature])) {
            return $this->normalize($definitions[$feature]);
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function fromDatabase(string $feature): ?array
    {
        if ($this->cache === null) {
            return $this->queryDatabase($feature);
        }

        $key = $this->cacheKey($feature);
        /** @var mixed $cached */
        $cached = $this->cache->get($key);
        if (\is_array($cached)) {
            return $cached === [] ? null : $cached;
        }

        $row = $this->queryDatabase($feature);
        $this->cache->put($key, $row ?? [], $this->cacheTtl);

        return $row;
    }

    /** @return array<string, mixed>|null */
    private function queryDatabase(string $feature): ?array
    {
        $container = \Tavaan\Engine\FeatureFlag\FeatureFlagResolver::container();
        if ($container === null || !$container->bound('db')) {
            return null;
        }

        try {
            /** @var \Tavaan\Engine\Database\ConnectionManager $db */
            $db = $container->make('db');
            $table = (string) ($this->config()['table'] ?? 'tvn_features');
            $row = $db->table($table)->where('name', $feature)->first();
        } catch (\Throwable) {
            return null;   // جدول migrate نشده → بی‌صدا به config برگرد
        }

        if ($row === null) {
            return null;
        }

        return $this->normalize([
            'enabled' => (bool) ($row['enabled'] ?? true),
            'default' => (bool) ($row['default_value'] ?? false),
            'rules' => $this->decodeJson($row['rules'] ?? null),
            'value' => \array_key_exists('value', (array) $row) && $row['value'] !== null
                ? $this->decodeJsonValue($row['value'])
                : null,
        ]);
    }

    /** @param array<string, mixed> $definition */
    private function persist(string $feature, array $definition): void
    {
        $container = FeatureFlagResolver::container();
        if ($container === null || !$container->bound('db')) {
            return;
        }

        try {
            /** @var \Tavaan\Engine\Database\ConnectionManager $db */
            $db = $container->make('db');
            $table = (string) ($this->config()['table'] ?? 'tvn_features');
            $now = \Tavaan\Support\Time\Clock::isoNow();

            $values = [
                'name' => $feature,
                'enabled' => (int) (bool) ($definition['enabled'] ?? true),
                'default_value' => (int) (bool) ($definition['default'] ?? false),
                'rules' => (string) \json_encode((array) ($definition['rules'] ?? []), JSON_UNESCAPED_UNICODE),
                'value' => \array_key_exists('value', $definition) && $definition['value'] !== null
                    ? (string) \json_encode($definition['value'], JSON_UNESCAPED_UNICODE)
                    : null,
                'updated_at' => $now,
            ];

            $existing = $db->table($table)->where('name', $feature)->first();
            if ($existing === null) {
                $db->table($table)->insert([...$values, 'created_at' => $now]);
            } else {
                $db->table($table)->where('name', $feature)->update($values);
            }
        } catch (\Throwable) {
            // best-effort — DB در دسترس نبود
        }
    }

    /** @param array<string, mixed> $definition → @return array<string, mixed> */
    private function normalize(array $definition): array
    {
        $out = [];
        if (\array_key_exists('enabled', $definition)) {
            $out['enabled'] = (bool) $definition['enabled'];
        }
        $out['default'] = (bool) ($definition['default'] ?? false);
        $rules = [];
        foreach ((array) ($definition['rules'] ?? []) as $rule) {
            if (\is_array($rule) && isset($rule['type'])) {
                $rules[] = $rule;
            }
        }
        $out['rules'] = $rules;
        if (\array_key_exists('value', $definition)) {
            $out['value'] = $definition['value'];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }
        $container = FeatureFlagResolver::container();
        if ($container !== null && $container->bound('config')) {
            $this->config = (array) $container->make('config')->get('features', []);
        }

        return $this->config ??= [];
    }

    private function cacheKey(string $feature): string
    {
        // 🔒 کلید با version — کش مسموم/کهنه با تغییر version باطل می‌شود
        return 'feature:def:' . $this->cacheVersion . ':' . $feature;
    }

    /** @return array<mixed>|null */
    private function decodeJson(mixed $raw): ?array
    {
        if (\is_array($raw)) {
            return $raw;
        }
        if (!\is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = \json_decode($raw, true);

        return \is_array($decoded) ? $decoded : null;
    }

    private function decodeJsonValue(mixed $raw): mixed
    {
        if (!\is_string($raw) || $raw === '') {
            return $raw;
        }
        $decoded = \json_decode($raw, true);

        return \json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }
}

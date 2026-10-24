<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Module
 * KitManager — کشف، اعتبارسنجی و نصب کیت‌های اپلیکیشنی (DOC-090 §8).
 */

namespace Tavaan\Engine\Module\Kit;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Module\ModuleRegistry;
use Tavaan\Engine\Module\ModuleManager;

/**
 * KitManager — نصب کیت = زنجیرهٔ گام‌های DOC-090 §8:
 *
 * ```
 * 1. requirements  2. modules(enable)  3. preset(config overlay)
 * 4. migrate       5. seed(roles/demo)  6. theme        7. status
 * ```
 *
 * خروجی پایدار نصب:
 * - `storage/framework/kits.status.php` — وضعیت کیت‌های نصب‌شده
 * - `config/kits.generated.php` — overlay پیکربندی (preset + theme)
 * - `tavaan.json → kits[]` — ثبت در manifest پروژه
 *
 * 🔒 Idempotent: نصب مجدد همان کیت بی‌اثر است (به‌جز به‌روزرسانی preset).
 *
 * @api 1.0
 * @see docs/05-applications/README.md
 */
final class KitManager
{
    /** @var array<string, KitManifest>|null slug/id → manifest */
    private ?array $discovered = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $basePath,
    ) {
    }

    // ─── کشف ──────────────────────────────────────────────────────────────

    /**
     * کشف کیت‌ها از kits/*&#47;kit.json.
     *
     * @return array<string, KitManifest> کلید = slug
     */
    public function discover(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        $kits = [];
        foreach (\glob($this->basePath . '/kits/*/kit.json') ?: [] as $file) {
            $raw = \file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            try {
                $data = \json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw KitException::invalidManifest($file, 'JSON نامعتبر');
            }
            if (!\is_array($data)) {
                continue;
            }

            $manifest = KitManifest::fromArray($data, \dirname($file));
            $kits[$manifest->slug] = $manifest;
            $kits[$manifest->id] = $manifest;
        }

        return $this->discovered = $kits;
    }

    public function get(string $kit): KitManifest
    {
        return $this->discover()[$kit] ?? throw KitException::notFound($kit);
    }

    public function has(string $kit): bool
    {
        return isset($this->discover()[$kit]);
    }

    /** @return list<string> نام presetهای موجود */
    public function presets(KitManifest $kit): array
    {
        $names = [];
        foreach (\glob($kit->path . '/presets/*.json') ?: [] as $file) {
            $name = \basename($file, '.json');
            $names[] = $name;
        }
        \sort($names);

        return $names;
    }

    /** وضعیت کیت‌های نصب‌شده (slug → info). @return array<string, array<string, mixed>> */
    public function installed(): array
    {
        $file = $this->statusFile();

        return \is_file($file) ? (array) require $file : [];
    }

    public function isInstalled(string $slug): bool
    {
        return isset($this->installed()[$slug]);
    }

    // ─── پیش‌نیازها ───────────────────────────────────────────────────────

    /**
     * بررسی پیش‌نیازهای kit.json → فهرست مشکلات (خالی = OK).
     *
     * @return list<string>
     */
    public function requirementsCheck(KitManifest $kit): array
    {
        $problems = [];
        $req = $kit->requirements;

        // PHP version (فقط >= پشتیبانی می‌شود)
        $php = (string) ($req['php'] ?? '');
        if (\preg_match('/^>=\s*(\d+\.\d+(?:\.\d+)?)/', $php, $m) === 1
            && \version_compare(PHP_VERSION, $m[1], '<')) {
            $problems[] = \sprintf('PHP >= %s لازم است (فعلی: %s)', $m[1], PHP_VERSION);
        }

        // افزونه‌ها
        foreach ((array) ($req['extensions'] ?? []) as $ext) {
            $ext = (string) $ext;
            if ($ext !== '' && !\extension_loaded($ext)) {
                $problems[] = 'افزونهٔ PHP نصب نیست: ' . $ext;
            }
        }

        // فضای دیسک
        $storageMb = (int) ($req['storage_mb'] ?? 0);
        if ($storageMb > 0) {
            $free = @\disk_free_space($this->basePath);
            if (\is_float($free) && $free < $storageMb * 1024 * 1024) {
                $problems[] = \sprintf('حداقل %dMB فضای خالی لازم است.', $storageMb);
            }
        }

        // حافظه
        $memoryMb = (int) ($req['memory_mb'] ?? 0);
        if ($memoryMb > 0) {
            $limit = $this->memoryLimitMb();
            if ($limit > 0 && $limit < $memoryMb) {
                $problems[] = \sprintf('memory_limit حداقل %dMB لازم است (فعلی: %dMB).', $memoryMb, $limit);
            }
        }

        // ماژول‌های اعلام‌شده باید وجود داشته باشند
        $registry = $this->optionalService(ModuleRegistry::class);
        if ($registry instanceof ModuleRegistry) {
            $missing = [];
            foreach ($kit->modules as $module) {
                if (!$registry->has($module['id'])) {
                    $missing[] = $module['id'];
                }
            }
            if ($missing !== []) {
                $problems[] = 'ماژول(های) یافت‌نشده: ' . \implode('، ', $missing);
            }
        }

        return $problems;
    }

    // ─── نصب ──────────────────────────────────────────────────────────────

    /**
     * نصب کیت (زنجیرهٔ کامل گام‌ها).
     *
     * @param array{preset?: string, with_demo?: bool, skip_migrate?: bool, skip_seed?: bool} $options
     *
     * @return array<string, mixed> گزارش نصب (steps/modules/roles)
     */
    public function install(string $kit, array $options = []): array
    {
        $manifest = $this->get($kit);
        $preset = (string) ($options['preset'] ?? $manifest->defaultPreset);
        $withDemo = (bool) ($options['with_demo'] ?? false);

        // 1) پیش‌نیازها
        $problems = $this->requirementsCheck($manifest);
        if ($problems !== []) {
            throw KitException::requirementsNotMet($manifest->slug, $problems);
        }

        $steps = [];

        // 2) فعال‌سازی ماژول‌ها (به ترتیب توپولوژیک)
        $enabledModules = $this->enableModules($manifest);
        $steps[] = 'ماژول‌ها: ' . ($enabledModules === [] ? 'همه فعال بودند' : \implode('، ', $enabledModules) . ' فعال شد');

        // 3) preset + theme → config overlay
        $overlay = $this->buildOverlay($manifest, $preset);
        $this->writeOverlay($manifest->slug, $overlay);
        $steps[] = 'preset «' . $preset . '» اعمال شد';

        // 4) migrate
        if (!($options['skip_migrate'] ?? false)) {
            $migrated = $this->runMigrations();
            $steps[] = 'migrate: ' . $migrated . ' migration';
        }

        // 5) theme — کپی ویوها به resources/views/vendor/<slug> (مکانیزم
        //    override استاندارد ViewFinder — AC-APP-04) + assets عمومی
        $themeReport = $this->installTheme($manifest);
        if ($themeReport !== '') {
            $steps[] = $themeReport;
        }

        // 6) rbac sync + نقش‌های کیت
        $rolesReport = $this->syncRbac($manifest);
        $steps[] = 'rbac: ' . $rolesReport['summary'];

        // 7) seed (demo)
        if ($withDemo && !($options['skip_seed'] ?? false)) {
            $seeded = $this->seedDemo($manifest);
            $steps[] = 'demo seed: ' . \implode('، ', $seeded);
        }

        // 8) ثبت وضعیت + tavaan.json
        $this->persistStatus($manifest, $preset, $withDemo);
        $this->registerInProjectManifest($manifest);
        $steps[] = 'وضعیت کیت ثبت شد (storage/framework/kits.status.php)';

        return [
            'kit' => $manifest->slug,
            'id' => $manifest->id,
            'version' => $manifest->version,
            'preset' => $preset,
            'demo' => $withDemo,
            'modules' => $enabledModules,
            'roles' => $rolesReport,
            'steps' => $steps,
        ];
    }

    // ─── گام‌های خصوصی ────────────────────────────────────────────────────

    /** @return list<string> ماژول‌هایی که تازه فعال شدند */
    private function enableModules(KitManifest $kit): array
    {
        $registry = $this->optionalService(ModuleRegistry::class);
        if (!$registry instanceof ModuleRegistry) {
            return [];
        }

        $manager = $this->optionalService(ModuleManager::class);
        if (!$manager instanceof ModuleManager) {
            $manager = null;
        }

        $required = $kit->requiredModules();
        $newlyEnabled = [];

        // ترتیب توپولوژیک — پیش‌نیازها زودتر فعال می‌شوند
        foreach ($registry->sorted() as $module) {
            if (!\in_array($module->id(), $required, true)) {
                continue;
            }
            if (!$module->enabled()) {
                $manager?->enable($module->id());
                $newlyEnabled[] = $module->id();
            }
        }

        return $newlyEnabled;
    }

    /**
     * ساخت overlay پیکربندی از preset + theme.
     *
     * @return array<string, mixed>
     */
    private function buildOverlay(KitManifest $kit, string $preset): array
    {
        $overlay = [];

        $presetFile = $kit->path . '/presets/' . $preset . '.json';
        if (!\is_file($presetFile)) {
            if ($preset !== $kit->defaultPreset || \count($this->presets($kit)) > 0) {
                throw KitException::presetNotFound($kit->slug, $preset);
            }
        } else {
            $raw = \file_get_contents($presetFile);
            $data = $raw === false ? null : \json_decode($raw, true);
            if (!\is_array($data)) {
                throw KitException::invalidManifest($presetFile, 'preset JSON نامعتبر');
            }
            $overlay = (array) ($data['config'] ?? []);
        }

        // theme → مسیر ویوهای پوسته برای ماژول هم‌نام کیت (قرارداد M9).
        // مسیر نسبی نسبت به base ذخیره می‌شود (portable بین محیط‌ها).
        if ($kit->theme !== '') {
            $themeViewsAbs = $kit->path . '/' . \trim($kit->theme, '/') . '/views';
            if (\is_dir($themeViewsAbs)) {
                $relative = \str_starts_with($themeViewsAbs, $this->basePath)
                    ? \ltrim(\substr($themeViewsAbs, \strlen($this->basePath)), '/\\')
                    : $themeViewsAbs;
                $overlay[$kit->slug]['theme']['views_path'] = $relative;
            }
        }

        $overlay['kits'][$kit->slug] = [
            'id' => $kit->id,
            'preset' => $preset,
            'version' => $kit->version,
        ];

        return $overlay;
    }

    /**
     * نوشتن overlay به‌تفکیک namespace پیکربندی.
     *
     * ConfigLoader هر فایل config/*&#47;.php را زیر کلید هم‌نام فایل merge
     * می‌کند (اولویت: engines < modules < config/ — DOC-032). بنابراین هر
     * کلید سطح-بالای overlay (blog/comment/kits/…) در فایل خودش نوشته و با
     * محتوای موجود deep-merge می‌شود (idempotent برای نصب مجدد).
     *
     * @param array<string, mixed> $overlay
     */
    private function writeOverlay(string $slug, array $overlay): void
    {
        foreach ($overlay as $namespace => $values) {
            if (!\is_array($values) || !\is_string($namespace) || $namespace === '') {
                continue;
            }

            // basename: محافظت در برابر کلید مخرب (path traversal)
            $file = $this->basePath . '/config/' . \basename($namespace) . '.php';
            $existing = \is_file($file) ? (array) require $file : [];

            $merged = $this->deepMerge($existing, $values);
            if ($namespace === 'kits') {
                $merged[$slug] = $values[$slug] ?? ($merged[$slug] ?? null);
                $merged = \array_filter($merged);
            }

            $php = "<?php\n\n/* تولیدشده توسط tvn kit:install — با kit:install بازتولید می‌شود. */\n\nreturn "
                . \var_export($merged, true) . ";\n";

            if (\file_put_contents($file, $php, LOCK_EX) === false) {
                throw KitException::invalidManifest($file, 'نوشتن overlay پیکربندی ناموفق بود');
            }
        }
    }

    /**
     * نصب theme کیت: ویوها → `resources/views/vendor/<slug>` (اولویت اول
     * ViewFinder) و assets → `public/assets/kits/<slug>`.
     */
    private function installTheme(KitManifest $kit): string
    {
        if ($kit->theme === '') {
            return '';
        }

        $themeRoot = $kit->path . '/' . \trim($kit->theme, '/');
        if (!\is_dir($themeRoot)) {
            return '';
        }

        $copied = [];

        $viewsSrc = $themeRoot . '/views';
        if (\is_dir($viewsSrc)) {
            $viewsDst = $this->basePath . '/resources/views/vendor/' . $kit->slug;
            $this->copyDir($viewsSrc, $viewsDst);
            $copied[] = 'ویوها → resources/views/vendor/' . $kit->slug;
        }

        $assetsSrc = $themeRoot . '/assets';
        if (\is_dir($assetsSrc)) {
            $assetsDst = $this->basePath . '/public/assets/kits/' . $kit->slug;
            $this->copyDir($assetsSrc, $assetsDst);
            $copied[] = 'assets → public/assets/kits/' . $kit->slug;
        }

        return $copied === [] ? '' : 'theme: ' . \implode(' + ', $copied);
    }

    /** کپی بازگشتی دایرکتوری (overwrite — idempotent). */
    private function copyDir(string $src, string $dst): void
    {
        if (!\is_dir($dst) && !@\mkdir($dst, 0775, true) && !\is_dir($dst)) {
            return;
        }

        foreach (\scandir($src) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $srcPath = $src . '/' . $entry;
            $dstPath = $dst . '/' . $entry;
            if (\is_dir($srcPath)) {
                $this->copyDir($srcPath, $dstPath);
            } else {
                @\copy($srcPath, $dstPath);
            }
        }
    }

    /** @return int تعداد migrationهای اجراشده */
    private function runMigrations(): int
    {
        $migrator = $this->optionalService(\Tavaan\Engine\Database\Migration\Migrator::class);
        if (!$migrator instanceof \Tavaan\Engine\Database\Migration\Migrator) {
            return 0;
        }

        try {
            return $migrator->run()->count();
        } catch (\Tavaan\Engine\Database\Migration\Exceptions\MigrationLockedException) {
            return -1;   // قفل — گزارش می‌شود
        }
    }

    /**
     * sync مجوزهای ماژول‌ها + ساخت نقش‌ها از seed/roles.json کیت.
     *
     * @return array{created: list<string>, summary: string}
     */
    private function syncRbac(KitManifest $kit): array
    {
        $created = [];

        $sync = $this->optionalService(\Tavaan\Engine\Rbac\PermissionSync::class);
        if ($sync instanceof \Tavaan\Engine\Rbac\PermissionSync) {
            $sync->syncFromModules($this->basePath);
        }

        $roles = $this->optionalService(\Tavaan\Engine\Rbac\RoleManager::class);
        $rolesFile = $kit->path . '/seed/roles.json';
        if (\is_file($rolesFile) && $roles instanceof \Tavaan\Engine\Rbac\RoleManager) {
            $raw = \file_get_contents($rolesFile);
            $data = $raw === false ? null : \json_decode($raw, true);
            if (\is_array($data)) {
                foreach ((array) ($data['roles'] ?? []) as $role) {
                    if (!\is_array($role) || !isset($role['key']) || !\is_string($role['key'])) {
                        continue;
                    }
                    $key = $role['key'];
                    if ($roles->findRole($key) === null) {
                        $roles->create($key, (string) ($role['title_fa'] ?? $role['title_en'] ?? $key));
                        $created[] = $key;
                    }
                    foreach ((array) ($role['permissions'] ?? []) as $permission) {
                        if (\is_string($permission) && $permission !== '') {
                            $roles->grantPermissionToRole($key, $permission);
                        }
                    }
                }
            }
        }

        return [
            'created' => $created,
            'summary' => $created === [] ? 'نقش‌ها موجود بودند' : 'نقش‌های جدید: ' . \implode('، ', $created),
        ];
    }

    /** @return list<string> منابع seed اجراشده */
    private function seedDemo(KitManifest $kit): array
    {
        $seeded = [];
        $db = $this->optionalService('db');
        $connection = $db !== null && \method_exists($db, 'connection') ? $db->connection() : null;

        // 1) seed ماژول‌های فعالِ کیت (database.seed_on_install / seeds/demo.php)
        $registry = $this->optionalService(ModuleRegistry::class);
        if ($registry instanceof ModuleRegistry) {
            $required = $kit->requiredModules();

            foreach ($registry->sorted() as $module) {
                if (!\in_array($module->id(), $required, true)) {
                    continue;
                }
                $file = $module->path() . '/seeds/demo.php';
                if (\is_file($file) && $this->runSeeder($file, $connection)) {
                    $seeded[] = $module->id();
                }
            }
        }

        // 2) demo data خود کیت (seed/demo/*.php)
        if ($kit->demoData !== null && $kit->demoData !== '') {
            foreach (\glob($kit->path . '/' . \trim($kit->demoData, '/') . '/*.php') ?: [] as $file) {
                if ($this->runSeeder($file, $connection)) {
                    $seeded[] = \basename($file, '.php');
                }
            }
        }

        return $seeded;
    }

    /** اجرای فایل seeder (callable با ۰ یا ۱ پارامتر Connection). */
    private function runSeeder(string $file, mixed $connection): bool
    {
        $seeder = require $file;
        if (!\is_callable($seeder)) {
            return false;
        }

        $params = (new \ReflectionFunction(\Closure::fromCallable($seeder)))->getNumberOfParameters();

        if ($params > 0) {
            $seeder($connection);
        } else {
            $seeder();
        }

        return true;
    }

    /** @param array<string, mixed> $extra */
    private function persistStatus(KitManifest $kit, string $preset, bool $withDemo): void
    {
        $status = $this->installed();
        $status[$kit->slug] = [
            'id' => $kit->id,
            'version' => $kit->version,
            'preset' => $preset,
            'demo' => $withDemo,
            'modules' => $kit->requiredModules(),
            'installed_at' => \date('c'),
        ];

        $file = $this->statusFile();
        @\mkdir(\dirname($file), 0775, true);
        \file_put_contents(
            $file,
            "<?php\n\nreturn " . \var_export($status, true) . ";\n",
            LOCK_EX,
        );
    }

    private function registerInProjectManifest(KitManifest $kit): void
    {
        $file = $this->basePath . '/tavaan.json';
        if (!\is_file($file)) {
            return;
        }
        $raw = \file_get_contents($file);
        $data = $raw === false ? null : \json_decode($raw, true);
        if (!\is_array($data)) {
            return;
        }

        $kits = (array) ($data['kits'] ?? []);
        if (\in_array($kit->id, $kits, true)) {
            return;
        }
        $kits[] = $kit->id;
        $data['kits'] = $kits;

        \file_put_contents(
            $file,
            (string) \json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            LOCK_EX,
        );
    }

    /**
     * ادغام عمیق: mapها ادغام، listها جایگزین (هم‌راستا با ConfigLoader).
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $over
     *
     * @return array<array-key, mixed>
     */
    private function deepMerge(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            if (\is_array($value) && isset($base[$key]) && \is_array($base[$key])
                && !$this->isList($value) && !$this->isList($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /** @param array<array-key, mixed> $array */
    private function isList(array $array): bool
    {
        return $array === [] || \array_is_list($array);
    }

    private function statusFile(): string
    {
        return $this->basePath . '/storage/framework/kits.status.php';
    }

    /**
     * resolve امن سرویس اختیاری — `bound()` برای هر کلاس موجود true است
     * (auto-wiring)، پس نبودِ binding واقعی با try/catch تشخیص داده می‌شود.
     */
    private function optionalService(string $abstract): ?object
    {
        try {
            $service = $this->container->make($abstract);
        } catch (\Throwable) {
            return null;
        }

        return \is_object($service) ? $service : null;
    }

    private function memoryLimitMb(): int
    {
        $limit = \trim((string) \ini_get('memory_limit'));
        if ($limit === '' || $limit === '-1') {
            return -1;
        }
        $value = (int) $limit;
        $unit = \strtolower(\substr($limit, -1));

        return match ($unit) {
            'g' => $value * 1024,
            'k' => (int) \max(1, \intdiv($value, 1024)),
            default => $value,
        };
    }
}

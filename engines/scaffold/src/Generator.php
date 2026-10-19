<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold;

use Tavaan\Support\Str;

/**
 * تَوان | Tavaan — Scaffold
 * Generator — پایهٔ تولیدکننده‌ها (DOC-077 §5).
 *
 * هر Generator نگاشت «مسیر هدف → محتوای رندرشده» برمی‌گرداند؛ نوشتن روی
 * دیسک (با پشتیبان `.bak` هنگام `--force`) کارِ `GenerateCommand` است.
 *
 * 🔒 امنیت (§8): نام کلاس با regex اعتبارسنجی می‌شود، مسیرها sanitize و
 * داخل `basePath` sandbox می‌شوند (path traversal → رد).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
abstract class Generator
{
    public function __construct(
        protected readonly StubRenderer $stubs,
        protected readonly string $basePath,
    ) {
    }

    /**
     * تولید فایل‌ها.
     *
     * @return array<string, string> مسیر مطلق هدف → محتوا
     *
     * @throws ScaffoldException نام/مسیر نامعتبر
     */
    abstract public function generate(GenerateCommand $cmd): array;

    // ─── مسیرها ───────────────────────────────────────────────────────────

    /** ریشهٔ پروژه (sandbox همهٔ مسیرهای تولیدی). */
    final protected function base(string $suffix = ''): string
    {
        $path = \rtrim(\str_replace('\\', '/', $this->basePath), '/');

        return $suffix === '' ? $path : $path . '/' . \ltrim($suffix, '/');
    }

    /**
     * مسیر فایل هدف — با sandboxing (باید زیر basePath بماند).
     *
     * @throws ScaffoldException
     */
    final protected function target(string $relativePath): string
    {
        $normalized = $this->normalizeRelative($relativePath);
        $full = $this->base($normalized);

        if (!\str_starts_with($full, $this->base() . '/')) {
            throw ScaffoldException::pathTraversal($relativePath);
        }

        return $full;
    }

    /**
     * @throws ScaffoldException
     */
    private function normalizeRelative(string $relativePath): string
    {
        $path = \str_replace('\\', '/', $relativePath);
        if (\str_contains($path, '..') || \str_starts_with($path, '/') || $path === '') {
            throw ScaffoldException::pathTraversal($relativePath);
        }

        $segments = [];
        foreach (\explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw ScaffoldException::pathTraversal($relativePath);
            }
            $segments[] = $segment;
        }

        return \implode('/', $segments);
    }

    // ─── زمینهٔ ماژول/اپ ──────────────────────────────────────────────────

    /**
     * زمینهٔ هدف: با `--module=shop` → `modules/shop/src` و `Modules\Shop`؛
     * بدون آن → `app/src` و `App`.
     *
     * @return array{namespace: string, src: string, module: ?string, modulePath: ?string}
     */
    final protected function context(GenerateCommand $cmd): array
    {
        return $this->contextFor($cmd->moduleOption());
    }

    /**
     * هم‌مانند `context()` ولی با نام ماژول صریح (برای delegation بین Generatorها).
     *
     * @return array{namespace: string, src: string, module: ?string, modulePath: ?string}
     */
    final protected function contextFor(?string $module): array
    {
        if ($module === null) {
            return [
                'namespace' => 'App',
                'src' => 'app/src',
                'module' => null,
                'modulePath' => null,
            ];
        }

        $slug = $this->validateKebab($module, 'module');
        $pascal = Str::pascal(\str_replace('-', ' ', $slug));

        return [
            'namespace' => 'Modules\\' . $pascal,
            'src' => 'modules/' . $slug . '/src',
            'module' => $slug,
            'modulePath' => 'modules/' . $slug,
        ];
    }

    // ─── اعتبارسنجی نام (§8) ──────────────────────────────────────────────

    /**
     * نام کلاس PascalCase.
     *
     * @throws ScaffoldException
     */
    final protected function validateClassName(string $name): string
    {
        if (\preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            throw ScaffoldException::invalidName($name, 'PascalCase (مثل ProductOrder)');
        }

        return $name;
    }

    /**
     * نام چندبخشی با `/` (مثل `Shop/ProductController`) → بخش‌ها PascalCase.
     *
     * @return list<string>
     *
     * @throws ScaffoldException
     */
    final protected function validateNamespacedName(string $name): array
    {
        $name = \str_replace('\\', '/', $name);
        if ($name === '' || \str_contains($name, '..') || \str_starts_with($name, '/') || \str_ends_with($name, '/')) {
            throw ScaffoldException::invalidName($name, 'مسیر کلاس معتبر (مثل Shop/ProductController)');
        }

        $segments = \explode('/', $name);
        foreach ($segments as $segment) {
            $this->validateClassName($segment);
        }

        return $segments;
    }

    /**
     * نام kebab-case (ماژول/موتور).
     *
     * @throws ScaffoldException
     */
    final protected function validateKebab(string $name, string $what = 'name'): string
    {
        if (\preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $name) !== 1) {
            throw ScaffoldException::invalidName($name, 'kebab-case (مثل order-items) — برای ' . $what);
        }

        return $name;
    }

    /**
     * نام snake_case (migration: create_products_table).
     *
     * @throws ScaffoldException
     */
    final protected function validateSnake(string $name, string $what = 'name'): string
    {
        if (\preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $name) !== 1) {
            throw ScaffoldException::invalidName($name, 'snake_case (مثل create_products_table) — برای ' . $what);
        }

        return $name;
    }

    // ─── رندر ─────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $vars
     */
    final protected function render(string $stubName, array $vars): string
    {
        return $this->stubs->render($stubName, $vars);
    }

    /**
     * متغیرهای پایهٔ مشترک همهٔ قالب‌ها.
     *
     * @param array{namespace: string, src: string, module: ?string, modulePath: ?string} $context
     *
     * @return array<string, mixed>
     */
    final protected function baseVars(GenerateCommand $cmd, array $context, string $class): array
    {
        unset($cmd);

        return [
            'namespace' => $context['namespace'],
            'class' => $class,
            'module' => $context['module'] ?? '',
        ];
    }
}

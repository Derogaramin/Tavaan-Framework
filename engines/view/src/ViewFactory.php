<?php

declare(strict_types=1);

namespace Tavaan\Engine\View;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\I18n\TranslatorInterface;
use Tavaan\Engine\View\Compilers\TemplateCompiler;
use Tavaan\Engine\View\Exceptions\ViewException;

/**
 * تَوان | Tavaan — View
 * ViewFactory — ساخت ویو، section/stack/component state و کامپایل کش‌شده.
 *
 * به‌عنوان `$__env` در قالب‌های کامپایل‌شده در دسترس است.
 * کش کامپایل: `storage/framework/views/<hash>.php` با hash شامل filemtime
 * (تغییر قالب → invalidate خودکار — DOC-043 §7).
 *
 * @api 1.0
 * @see docs/03-engines/view.md
 */
final class ViewFactory
{
    /** سقف عمق layout برای includeها (مشابه View::MAX_LAYOUT_DEPTH). */
    private const MAX_INCLUDE_LAYOUT_DEPTH = 10;

    private ViewFinder $finder;

    private TemplateCompiler $compiler;

    private DirectiveRegistry $directives;

    /** @var array<string, mixed> دادهٔ مشترک همهٔ ویوها */
    private array $shared = [];

    /** @var array<string, string> sectionها */
    private array $sections = [];

    /** @var list<string> پشتهٔ section در حال ضبط */
    private array $sectionStack = [];

    /** @var array<string, list<string>> stackها (push) */
    private array $pushes = [];

    /** @var list<string> */
    private array $pushStack = [];

    /** @var list<array{name: string, data: array<string, mixed>, slots: array<string, string>}> */
    private array $componentStack = [];

    /** @var array<string, string> slotهای در حال ضبط (component جاری) */
    private array $currentSlots = [];

    /** @var list<string> */
    private array $slotStack = [];

    /** @var list<string|null> پشتهٔ layoutها (@extends) */
    private array $extendsStack = [];

    private int $renderDepth = 0;

    /** عمق چرخهٔ رندر سطح-ویو (View::render) — برای بازنشانی وضعیت sectionها */
    private int $viewRenderDepth = 0;

    private string $compiledPath;

    private int $maxDepth;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly ?ContainerInterface $container = null,
        array $config = [],
    ) {
        $this->finder = new ViewFinder(
            (array) ($config['paths'] ?? [\defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/resources/views' : '']),
            (string) ($config['extension'] ?? '.tv.php'),
        );
        foreach ((array) ($config['namespaces'] ?? []) as $ns => $path) {
            $this->finder->addNamespace((string) $ns, (string) $path);
        }

        $this->compiledPath = (string) ($config['compiled'] ?? (\defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/storage/framework/views' : \sys_get_temp_dir()));
        $this->maxDepth = (int) ($config['max_depth'] ?? 10);
        $this->directives = new DirectiveRegistry();
        $this->compiler = new TemplateCompiler($this->directives);
    }

    // ─── API عمومی ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function make(string $template, array $data = []): View
    {
        return new View($this, $template, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        return $this->make($template, $data)->render();
    }

    public function exists(string $template): bool
    {
        return $this->finder->exists($template);
    }

    public function share(string|array $key, mixed $value = null): void
    {
        if (\is_array($key)) {
            $this->shared = [...$this->shared, ...$key];

            return;
        }
        $this->shared[$key] = $value;
    }

    /** @return array<string, mixed> */
    public function shared(): array
    {
        return $this->shared;
    }

    public function addNamespace(string $namespace, string $path): void
    {
        $this->finder->addNamespace($namespace, $path);
    }

    public function addLocation(string $path): void
    {
        $this->finder->addLocation($path);
    }

    public function finder(): ViewFinder
    {
        return $this->finder;
    }

    /** ثبت directive سفارشی (فقط Provider). @param \Closure(?string): string $handler */
    public function directive(string $name, \Closure $handler): void
    {
        $this->directives->register($name, $handler);
    }

    public function directives(): DirectiveRegistry
    {
        return $this->directives;
    }

    // ─── کامپایل + کش ─────────────────────────────────────────────────────

    public function compiledPathFor(string $template): string
    {
        $path = $this->finder->find($template);
        $hash = \hash('xxh128', $path . '|' . \filemtime($path) . '|' . TemplateCompiler::VERSION);

        return $this->compiledPath . '/' . $hash . '.php';
    }

    public function isExpired(string $template): bool
    {
        $compiled = $this->compiledPathFor($template);

        return !\is_file($compiled) || \filemtime($compiled) < \filemtime($this->finder->find($template));
    }

    /**
     * کامپایل (در صورت نیاز) و بازگرداندن مسیر فایل کامپایل‌شده.
     *
     * @param bool $verify اعتبارسنجی با php -l (گران — فقط view:cache)
     */
    public function compileIfNeeded(string $template, bool $verify = false): string
    {
        $compiled = $this->compiledPathFor($template);
        if (\is_file($compiled)) {
            return $compiled;
        }

        $source = $this->finder->find($template);
        $php = $this->compiler->compile((string) \file_get_contents($source));

        if (!\is_dir($this->compiledPath)) {
            \mkdir($this->compiledPath, 0775, true);
        }

        $tmp = $compiled . '.' . \bin2hex(\random_bytes(3)) . '.tmp';
        \file_put_contents($tmp, $php);

        if ($verify) {
            // 🔒 اعتبارسنجی خروجی کامپایلر — proc_open با آرایه (بدون shell)
            $check = $this->phpLint($tmp);
            if ($check !== null) {
                @\unlink($tmp);
                throw ViewException::compilationFailed($template, $check);
            }
        }

        \rename($tmp, $compiled);

        return $compiled;
    }

    /** کامپایل مستقیم رشته (تست). */
    public function compileString(string $template): string
    {
        return $this->compiler->compile($template);
    }

    public function flushCompiled(): void
    {
        foreach (\glob($this->compiledPath . '/*.php') ?: [] as $file) {
            @\unlink($file);
        }
    }

    // ─── ارزیابی (توسط View) ──────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function evaluate(string $compiledPath, array $data): string
    {
        $renderer = static function (string $__path, array $__data, ViewFactory $__env): string {
            // 🔒 کلیدهای __ دار حذف شوند تا متغیرهای داخلی ($__env/…) غیرقابل دستکاری بمانند
            foreach (\array_keys($__data) as $__k) {
                if (\is_string($__k) && \str_starts_with($__k, '__')) {
                    unset($__data[$__k]);
                }
            }
            // extract روی دادهٔ توسعه‌دهنده (نه ورودی خام کاربر) با EXTR_SKIP —
            // متغیرهای از پیش تعریف‌شده ($__path/$__data/$__env) هرگز بازنویسی نمی‌شوند.
            \extract($__data, EXTR_SKIP);
            \ob_start();
            try {
                include $__path;
            } catch (\Throwable $e) {
                \ob_end_clean();
                throw $e;
            }

            return (string) \ob_get_clean();
        };

        return $renderer($compiledPath, [...$this->shared, ...$data], $this);
    }

    /**
     * آغاز یک چرخهٔ رندر سطح-ویو (توسط View::render).
     *
     * 🔒 در سطح اول، state قالب‌ها (section/push/slot/extends) پاک می‌شود تا
     * در فرایندهای مقیم/تست‌ها رندرهای متوالی روی هم انباشته نشوند
     * (stopSection محتوای section را append می‌کند). بافرهای نشتی‌کردهٔ
     * sectionهای نیمه‌کاره (استثنای رندر قبلی) نیز بسته می‌شوند.
     */
    public function beginViewRender(): void
    {
        if ($this->viewRenderDepth > 0) {
            $this->viewRenderDepth++;

            return;
        }
        $this->viewRenderDepth = 1;

        // بستن بافرهای بازِ مانده از رندر ناموفق قبلی
        while ($this->sectionStack !== []) {
            \array_pop($this->sectionStack);
            if (\ob_get_level() > 0) {
                \ob_end_clean();
            }
        }
        while ($this->pushStack !== []) {
            \array_pop($this->pushStack);
            if (\ob_get_level() > 0) {
                \ob_end_clean();
            }
        }
        while ($this->slotStack !== []) {
            \array_pop($this->slotStack);
            if (\ob_get_level() > 0) {
                \ob_end_clean();
            }
        }

        $this->sections = [];
        $this->pushes = [];
        $this->currentSlots = [];
        $this->componentStack = [];
        $this->extendsStack = [];
    }

    /** پایان چرخهٔ رندر سطح-ویو. */
    public function endViewRender(): void
    {
        $this->viewRenderDepth = \max(0, $this->viewRenderDepth - 1);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function evaluateTemplate(string $template, array $data): string
    {
        if ($this->renderDepth >= $this->maxDepth) {
            throw ViewException::maxDepth($template, $this->maxDepth);
        }

        $this->renderDepth++;
        try {
            return $this->evaluate($this->compileIfNeeded($template), $data);
        } finally {
            $this->renderDepth--;
        }
    }

    // ─── Layout / Section (API داخلی قالب‌ها: $__env->…) ─────────────────

    public function extends(string $layout): void
    {
        $this->extendsStack[] = $layout;
    }

    public function popExtends(): ?string
    {
        return \array_pop($this->extendsStack);
    }

    public function startSection(string $name): void
    {
        $this->sectionStack[] = $name;
        \ob_start();
    }

    public function stopSection(): void
    {
        if ($this->sectionStack === []) {
            throw new \LogicException('stopSection() without startSection().');
        }
        $name = \array_pop($this->sectionStack);
        $content = (string) \ob_get_clean();
        $this->sections[$name] = ($this->sections[$name] ?? '') . $content;
    }

    /** @section('name', 'inline value') */
    public function inlineSection(string $name, mixed $value = null): void
    {
        $this->sections[$name] = \is_string($value) ? $value : (string) $value;
    }

    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    // ─── Push / Stack ─────────────────────────────────────────────────────

    public function startPush(string $stack): void
    {
        $this->pushStack[] = $stack;
        \ob_start();
    }

    public function stopPush(): void
    {
        if ($this->pushStack === []) {
            throw new \LogicException('stopPush() without startPush().');
        }
        $stack = \array_pop($this->pushStack);
        $this->pushes[$stack][] = (string) \ob_get_clean();
    }

    public function yieldStack(string $stack): string
    {
        return \implode('', $this->pushes[$stack] ?? []);
    }

    // ─── Component ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $attributes
     */
    public function startComponent(string $name, array $attributes = []): void
    {
        $this->componentStack[] = ['name' => $name, 'data' => $attributes, 'slots' => []];
        $this->currentSlots = [];
        \ob_start();
    }

    public function startSlot(string $name): void
    {
        $this->slotStack[] = $name;
        \ob_start();
    }

    public function endSlot(): void
    {
        if ($this->slotStack === []) {
            throw new \LogicException('endSlot() without startSlot().');
        }
        $name = \array_pop($this->slotStack);
        $this->currentSlots[$name] = (string) \ob_get_clean();
    }

    /** رندر کامپوننت (قالب components/<name>) با slotها و attribute bag. */
    public function renderComponent(): string
    {
        if ($this->componentStack === []) {
            throw new \LogicException('renderComponent() without startComponent().');
        }

        $component = \array_pop($this->componentStack);
        $content = (string) \ob_get_clean();

        // slotهای default از content (بدون slotهای ضبط‌شده که قبلاً مصرف شدند)
        $slots = $this->currentSlots;
        $this->currentSlots = [];

        // همهٔ attributeها هم به‌عنوان data (برای @props/متغیرها) و هم در bag
        // در دسترس‌اند؛ bag برای رندر `{{ $attributes }}` (escape شده).
        $data = [
            ...$component['data'],
            'slot' => new SafeString($content),
            ...$slots,
            'attributes' => new ComponentAttributeBag($component['data']),
        ];

        return $this->render('components.' . $component['name'], $data);
    }

    // ─── Include ──────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function renderInclude(string $template, array $data = []): string
    {
        // 🔒 M9: چرخهٔ extends مستقل برای include — popExtends نباید layout
        // قالبِ والد را از پشتهٔ مشترک مصرف کند (باگ: @include درون section
        // باعث گم‌شدن کل خروجی صفحه می‌شد).
        $savedExtends = $this->extendsStack;
        $this->extendsStack = [];

        try {
            $content = $this->evaluateTemplate($template, $data);

            $depth = 0;
            while (($parent = $this->popExtends()) !== null) {
                if (++$depth > self::MAX_INCLUDE_LAYOUT_DEPTH) {
                    throw ViewException::maxDepth((string) $parent, self::MAX_INCLUDE_LAYOUT_DEPTH);
                }
                $content = $this->evaluateTemplate($parent, [...$data, '__content' => $content]);
            }

            return $content;
        } finally {
            $this->extendsStack = $savedExtends;
        }
    }

    /** @param array<string, mixed> $data */
    public function renderIncludeIf(string $template, array $data = []): string
    {
        return $this->exists($template) ? $this->render($template, $data) : '';
    }

    /** @param array<string, mixed> $data */
    public function renderIncludeWhen(bool $condition, string $template, array $data = []): string
    {
        return $condition ? $this->render($template, $data) : '';
    }

    /** @param list<string> $templates */
    public function renderIncludeFirst(array $templates, array $data = []): string
    {
        foreach ($templates as $template) {
            if ($this->exists((string) $template)) {
                return $this->render((string) $template, $data);
            }
        }

        throw ViewException::notFound(\implode(', ', $templates));
    }

    // ─── helperهای قالب ───────────────────────────────────────────────────

    public function csrfToken(): string
    {
        if ($this->container !== null && $this->container->bound('session')) {
            try {
                $session = $this->container->make('session');
                if (\is_object($session) && \method_exists($session, 'token')) {
                    return (string) $session->token();
                }
            } catch (\Throwable) {
                // بدون session → توکن خالی
            }
        }

        return '';
    }

    public function auth(): bool
    {
        if ($this->container !== null && $this->container->bound('auth')) {
            try {
                return (bool) $this->container->make('auth')->check();
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    public function isProduction(): bool
    {
        if ($this->container !== null && $this->container->bound('config')) {
            return (string) $this->container->make('config')->get('app.env', 'production') === 'production';
        }

        return true;
    }

    /** @param array<string, mixed> $replace */
    public function trans(string $key, array $replace = []): string
    {
        if ($this->container !== null && $this->container->bound('translator')) {
            $translator = $this->container->make('translator');
            if ($translator instanceof TranslatorInterface) {
                return $translator->get($key, $replace);
            }
        }

        return $key;
    }

    /** @param array<string, mixed> $replace */
    public function choice(string $key, int $number, array $replace = []): string
    {
        if ($this->container !== null && $this->container->bound('translator')) {
            $translator = $this->container->make('translator');
            if ($translator instanceof TranslatorInterface) {
                return $translator->choice($key, $number, $replace);
            }
        }

        return $key;
    }

    /** @param array<string, mixed> $replace */
    public function can(string $ability, array $replace = []): bool
    {
        if ($this->container !== null && $this->container->bound('gate')) {
            try {
                return (bool) $this->container->make('gate')->allows($ability, ...$replace);
            } catch (\Throwable) {
                return false;
            }
        }

        return false;   // بدون RBAC (M4) → deny
    }

    /**
     * `php -l` بدون shell (proc_open با آرایه — F-SHELL safe).
     *
     * @return string|null پیام خطا یا null اگر سالم
     */
    private function phpLint(string $file): ?string
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @\proc_open([PHP_BINARY, '-l', '-n', $file], $descriptors, $pipes);
        if (!\is_resource($process)) {
            return null;   // بدون امکان بررسی — سخت نگیر
        }

        $out = (string) \stream_get_contents($pipes[1]);
        $err = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $exit = \proc_close($process);

        return $exit === 0 ? null : \trim($out . $err);
    }
}

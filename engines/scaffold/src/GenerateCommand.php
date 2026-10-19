<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold;

use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Scaffold
 * GenerateCommand — پایهٔ مشترک همهٔ دستورات `make:*` (DOC-077 §5/§7).
 *
 * جریان: resolve Generator از GeneratorManager → generate → نوشتن فایل‌ها با
 * محافظت در برابر بازنویسی (اخطار؛ `--force` → پشتیبان `.bak` + بازنویسی).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
abstract class GenerateCommand extends Command
{
    /** کلید Generator در GeneratorManager (مثل `model`). */
    abstract protected function generatorKey(): string;

    public function handle(): int
    {
        /** @var GeneratorManager $manager */
        $manager = $this->app->make(GeneratorManager::class);

        try {
            $files = $manager->generate($this->generatorKey(), $this);
        } catch (ScaffoldException $e) {
            $this->error('  ✗ ' . ($e->hint ?? $e->messageId));
            $this->output->writeln(
                '    ' . $e->messageId . ' — ' . (string) \json_encode($e->context, JSON_UNESCAPED_UNICODE),
                'comment',
            );

            return self::FAILURE;
        }

        if ($files === []) {
            $this->info('  هیچ فایلی تولید نشد.');

            return self::SUCCESS;
        }

        $base = \str_replace('\\', '/', $this->basePath());
        $created = 0;
        $skipped = 0;

        foreach ($files as $path => $content) {
            $relative = \str_starts_with($path, $base . '/') ? \substr($path, \strlen($base) + 1) : $path;

            if (\file_exists($path)) {
                if (!$this->forceOption()) {
                    $this->output->writeln('  ⚠ فایل ' . $relative . ' وجود دارد — با --force بازنویسی کنید.', 'comment');
                    $skipped++;
                    continue;
                }
                @\copy($path, $path . '.bak');   // پشتیبان پیش از بازنویسی (DOC-077 §7)
            }

            $dir = \dirname($path);
            if (!\is_dir($dir) && !\mkdir($dir, 0775, true) && !\is_dir($dir)) {
                $this->error('  ✗ ساخت دایرکتوری ناموفق: ' . $dir);

                return self::FAILURE;
            }

            \file_put_contents($path, $content);
            $this->output->writeln('  ✓ ' . $relative, 'info');
            $created++;
        }

        $this->afterGenerate($created, $skipped);

        $summary = \sprintf('  %d فایل ساخته شد', $created);
        if ($skipped > 0) {
            $summary .= \sprintf('، %d مورد موجود (بدون بازنویسی)', $skipped);
        }
        $this->info($summary . '.');

        return self::SUCCESS;
    }

    /** قلاب پس از تولید (مثل راهنمای ثبت ماژول) — پیش‌فرض: بی‌اثر. */
    protected function afterGenerate(int $created, int $skipped): void
    {
    }

    // ─── دسترسی عمومی برای Generatorها ───────────────────────────────────

    public function nameArgument(): string
    {
        $name = $this->argument('name');

        return \is_string($name) ? \trim($name) : '';
    }

    public function moduleOption(): ?string
    {
        $module = $this->option('module');

        return \is_string($module) && $module !== '' ? $module : null;
    }

    public function forceOption(): bool
    {
        return (bool) $this->option('force');
    }

    /** مقدار یک option تعریف‌شده در امضای زیرکلاس. */
    public function opt(string $name): mixed
    {
        return $this->option($name);
    }

    public function basePath(): string
    {
        return \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : '';
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;

/**
 * `tvn engine:validate {name?}` — اعتبارسنجی عمیق manifest موتورها
 * (DoD §2: «module.json معتبر (tvn engine:validate <name>)»).
 *
 * بررسی‌ها: فیلدهای الزامی، قالب id (tavaan.engine.<slug>)، semver،
 * وجود دایرکتوری autoload (از جمله مسیرهای نسبی ../)، وجود فایل کلاس
 * providerها، وجود سند docs، و تطبیق با docs/manifest.json.
 *
 * 🔒 ممیزی M0–M8 (R2): وعدهٔ DoD/DOC-083 بود و پیاده‌سازی نشده بود.
 *
 * @api 1.0
 * @see docs/04-structure/module-schema.md
 */
final class EngineValidateCommand extends Command
{
    protected string $signature = 'engine:validate {name? : slug موتور (خالی = همه)} {--json : خروجی ماشین‌خوان}';

    protected string $description = 'اعتبارسنجی عمیق manifest موتورها (module.json + manifest.json)';

    private const REQUIRED = ['id', 'name', 'version', 'autoload', 'providers'];

    public function handle(): int
    {
        $base = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH : \dirname(__DIR__, 4);
        $asJson = (bool) $this->option('json');
        $only = (string) ($this->argument('name') ?: '');

        /** @var array<int, array{slug?: string, namespace?: string, doc?: string}> $manifestEngines */
        $manifestEngines = [];
        $manifestFile = $base . '/docs/manifest.json';
        if (\is_file($manifestFile)) {
            /** @var mixed $decoded */
            $decoded = \json_decode((string) \file_get_contents($manifestFile), true);
            foreach ((array) (\is_array($decoded) ? ($decoded['engines'] ?? []) : []) as $entry) {
                if (\is_array($entry) && isset($entry['slug'])) {
                    $manifestEngines[(string) $entry['slug']] = $entry;
                }
            }
        }

        $errors = [];
        $warnings = [];
        $checked = 0;

        foreach ((array) (\glob($base . '/engines/*', GLOB_ONLYDIR) ?: []) as $engineDir) {
            $slug = \basename((string) $engineDir);
            if ($only !== '' && $slug !== $only) {
                continue;
            }
            $manifest = (string) $engineDir . '/module.json';
            if (!\is_file($manifest)) {
                $errors[] = $slug . ': module.json وجود ندارد';

                continue;
            }
            /** @var mixed $decoded */
            $decoded = \json_decode((string) \file_get_contents($manifest), true);
            if (!\is_array($decoded)) {
                $errors[] = $slug . ': module.json نامعتبر (JSON)';

                continue;
            }
            $checked++;
            /** @var array<string, mixed> $data */
            $data = $decoded;

            foreach (self::REQUIRED as $field) {
                if (!\array_key_exists($field, $data)) {
                    $errors[] = $slug . ': فیلد الزامی مفقود: ' . $field;
                }
            }

            $id = (string) ($data['id'] ?? '');
            if ($id !== '' && !\str_starts_with($id, 'tavaan.engine.' . $slug)) {
                // زیرموتورها (cookie/jalali/pagination) id خاص خود را دارند — هشدار
                $warnings[] = $slug . ': id با slug هم‌خوانی ندارد: ' . $id;
            }
            $version = (string) ($data['version'] ?? '');
            if ($version !== '' && \preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z\-.]+)?(\+[0-9A-Za-z\-.]+)?$/', $version) !== 1) {
                $errors[] = $slug . ': version خارج از semver: ' . $version;
            }

            // autoload psr-4 — دایرکتوری موجود (مسیرهای ../ نسبت به دایرکتوری موتور)
            foreach ((array) ($data['autoload']['psr-4'] ?? []) as $prefix => $path) {
                $full = (string) $engineDir . '/' . (string) $path;
                if (!\is_dir($full)) {
                    $errors[] = $slug . ': دایرکتوری autoload مفقود: ' . (string) $path;
                }
            }

            // providers — فایل کلاس در جایی از engines/ یا core/
            foreach ((array) ($data['providers'] ?? []) as $provider) {
                $classFile = \str_replace('\\', '/', (string) $provider);
                $classFile = \substr($classFile, (int) \strrpos($classFile, '/') + 1) . '.php';
                $found = \glob($base . '/engines/*/**/' . $classFile) ?: [];
                $found2 = \glob($base . '/core/**/' . $classFile) ?: [];
                if ($found === [] && $found2 === []) {
                    // glob ستارهٔ تودرتو ندارد — جست‌وجوی بازگشتی سبک
                    $found = $this->findClassFile($base, $classFile);
                }
                if ($found === []) {
                    $errors[] = $slug . ': فایل کلاس provider یافت نشد: ' . (string) $provider;
                }
            }

            // docs
            $docs = (string) ($data['docs'] ?? '');
            if ($docs !== '' && !\is_file($base . '/' . $docs)) {
                $errors[] = $slug . ': سند docs مفقود: ' . $docs;
            }

            // تطبیق با docs/manifest.json
            $entry = $manifestEngines[$slug] ?? null;
            if ($entry === null) {
                $warnings[] = $slug . ': در docs/manifest.json فهرست نشده';
            } else {
                $ns = (string) ($data['namespace'] ?? '');
                if ($ns !== '' && isset($entry['namespace']) && \rtrim((string) $entry['namespace'], '\\') !== \rtrim($ns, '\\')) {
                    $warnings[] = $slug . ': namespace با manifest.json differs (' . $ns . ' vs ' . $entry['namespace'] . ')';
                }
                if ($docs !== '' && isset($entry['doc']) && (string) $entry['doc'] !== $docs) {
                    $warnings[] = $slug . ': مسیر doc با manifest.json differs';
                }
            }
        }

        if ($asJson) {
            $this->line((string) \json_encode([
                'ok' => $errors === [],
                'checked' => $checked,
                'errors' => $errors,
                'warnings' => $warnings,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $errors === [] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($errors as $error) {
            $this->error('  ✗ ' . $error);
        }
        foreach ($warnings as $warning) {
            $this->warn('  ⚠ ' . $warning);
        }
        $this->info('  ' . ($errors === [] ? '✓' : '✗') . ' engine:validate — ' . $checked . ' موتور، '
            . \count($errors) . ' خطا، ' . \count($warnings) . ' هشدار.');

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    private function findClassFile(string $base, string $classFile): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($base . '/engines', \FilesystemIterator::SKIP_DOTS),
                static fn (\SplFileInfo $f): bool => !\in_array($f->getFilename(), ['vendor', 'tests', 'benchmarks'], true),
            ),
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $classFile) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Console;

use Tavaan\Engine\Api\OpenApi\OpenApiGenerator;
use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Api
 * ApiDocsCommand — تولید سند OpenAPI (DOC-044 §4).
 *
 * ```bash
 * tvn api:docs --version=v1 --format=yaml --output=docs/openapi.yaml
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
final class ApiDocsCommand extends Command
{
    protected string $signature = 'api:docs {--version=v1 : نگارش API} {--format=yaml : قالب خروجی (yaml|json)} {--output= : مسیر فایل (خالی = stdout)}';

    protected string $description = 'تولید سند OpenAPI 3.1 از routeها و attributeها';

    public function handle(): int
    {
        $version = (string) ($this->option('version') ?? 'v1');
        $format = \mb_strtolower((string) ($this->option('format') ?? 'yaml'));
        $output = (string) ($this->option('output') ?? '');

        if (!\in_array($format, ['yaml', 'json'], true)) {
            $this->error('قالب نامعتبر: ' . $format . ' (فقط yaml|json)');

            return self::FAILURE;
        }

        /** @var OpenApiGenerator $generator */
        $generator = $this->app->make(OpenApiGenerator::class);
        $spec = $generator->generate($version);
        $document = $format === 'json' ? $generator->toJson($spec) : $generator->toYaml($spec);

        if ($output === '') {
            $this->line($document);

            return self::SUCCESS;
        }

        // 🔒 محافظ مسیر: output باید داخل base path بماند
        $base = \defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : (\getcwd() ?: '.');
        $target = \str_starts_with($output, '/') ? $output : $base . '/' . \ltrim($output, '/');
        $dir = \dirname($target);
        if (!\is_dir($dir) && !\mkdir($dir, 0775, true) && !\is_dir($dir)) {
            $this->error('ساخت پوشه ناموفق: ' . $dir);

            return self::FAILURE;
        }

        $realBase = \realpath($base);
        $real = \realpath($dir);
        if ($real === false || $realBase === false || !\str_starts_with($real . '/', \rtrim($realBase, '/') . '/')) {
            $this->error('مسیر خروجی باید داخل پروژه باشد: ' . $output);

            return self::FAILURE;
        }

        if (\file_put_contents($target, $document . "\n") === false) {
            $this->error('نوشتن فایل ناموفق: ' . $target);

            return self::FAILURE;
        }

        $paths = \count((array) ($spec['paths'] ?? []));
        $this->info(\sprintf('✅ سند OpenAPI (%s) با %d مسیر نوشته شد: %s', \strtoupper($format), $paths, $target));

        return self::SUCCESS;
    }
}

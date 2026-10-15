<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config;

use Tavaan\Support\Arr;

/**
 * تَوان | Tavaan — Config
 * ConfigLoader — بارگذاری و ادغام فایل‌های پیکربندی با اولویت (DOC-032 §5.3).
 *
 * اولویت (کم → زیاد): engines/*&#47;config → modules/*&#47;config → config/ → config/env/{env}.
 * قاعدهٔ ادغام: listها جایگزین، mapها ادغام عمیق.
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class ConfigLoader
{
    /**
     * @param string $basePath ریشهٔ پروژه
     */
    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * بارگذاری کامل همهٔ فایل‌های config.
     *
     * @return array<string, mixed> درخت پیکربندی (namespace → data)
     */
    public function load(string $environment = 'production'): array
    {
        $items = [];

        // 1) پیش‌فرض موتورها (کمترین اولویت)
        foreach ($this->filesIn('engines/*/config/*.php') as $file) {
            $this->mergeFile($items, $file);
        }

        // 2) ماژول‌ها
        foreach ($this->filesIn('modules/*/config/*.php') as $file) {
            $this->mergeFile($items, $file);
        }

        // 3) اپلیکیشن (config/) — بدون پوشهٔ schema
        foreach ($this->filesIn('config/*.php') as $file) {
            $this->mergeFile($items, $file);
        }

        // 4) override محیطی (config/env/{environment}.php)
        $envFile = $this->basePath . '/config/env/' . $environment . '.php';
        if (\is_file($envFile)) {
            $this->mergeFile($items, $envFile);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $items
     */
    private function mergeFile(array &$items, string $file): void
    {
        $namespace = \basename($file, '.php');

        /** @var mixed $loaded */
        $loaded = require $file;
        if (!\is_array($loaded)) {
            return;
        }

        $existing = $items[$namespace] ?? [];
        $items[$namespace] = \is_array($existing) ? Arr::mergeDeep($existing, $loaded) : $loaded;
    }

    /**
     * @return list<string> فایل‌های مطابقی (مرتب — خروجی قطعی)
     */
    private function filesIn(string $pattern): array
    {
        $files = \glob($this->basePath . '/' . $pattern) ?: [];
        \sort($files);

        return $files;
    }
}

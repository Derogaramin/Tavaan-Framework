<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade;

/**
 * تَوان | Tavaan — Upgrade
 * PreChecker — بررسی سازگاری پیش از ارتقا (DOC-081E §2 گام ۱ / §9 T-05).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class PreChecker
{
    /**
     * @param array<string, mixed> $config بخش upgrade.precheck
     *
     * @return list<string> دلایل ناسازگاری (خالی = سالم)
     */
    public function check(array $config): array
    {
        $problems = [];

        $phpMin = (string) ($config['php_min'] ?? '8.2.0');
        if (\version_compare(PHP_VERSION, $phpMin, '<')) {
            $problems[] = \sprintf('PHP %s لازم است (فعلی: %s)', $phpMin, PHP_VERSION);
        }

        foreach ((array) ($config['extensions'] ?? []) as $extension) {
            if (!\extension_loaded((string) $extension)) {
                $problems[] = 'افزونهٔ PHP مفقود: ' . $extension;
            }
        }

        $minFreeMb = (int) ($config['min_free_mb'] ?? 0);
        if ($minFreeMb > 0) {
            $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : __DIR__;
            $free = @\disk_free_space($base);
            if ($free !== false && $free < $minFreeMb * 1048576) {
                $problems[] = \sprintf(
                    'فضای آزاد ناکافی: %dMB لازم، %dMB موجود',
                    $minFreeMb,
                    (int) ($free / 1048576),
                );
            }
        }

        return $problems;
    }

    /** خلاصهٔ وضعیت (برای upgrade:check). @return array<string, mixed> */
    public function report(array $config): array
    {
        return [
            'php' => PHP_VERSION,
            'php_min' => (string) ($config['php_min'] ?? '8.2.0'),
            'problems' => $this->check($config),
        ];
    }
}

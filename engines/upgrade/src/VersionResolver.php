<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade;

use Tavaan\Engine\Upgrade\Exceptions\UpgradeException;

/**
 * تَوان | Tavaan — Upgrade
 * VersionResolver — مسیر ارتقای گام‌به‌گام (DOC-081E §5).
 *
 * نسخه‌های موجود = دایرکتوری‌های `upgrades/<version>/` + نسخهٔ هدف.
 * 🔒 پرش از نسخهٔ میانی مجاز نیست؛ downgrade مسیر ارتقا نیست (rollback جداست).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class VersionResolver
{
    public function __construct(private readonly string $upgradesPath)
    {
    }

    /**
     * نسخه‌های دارای patch (صعودی بر اساس semver).
     *
     * @return list<string>
     */
    public function availableVersions(): array
    {
        $versions = [];
        foreach (\glob($this->upgradesPath . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = \basename($dir);
            if (self::isSemver($name)) {
                $versions[] = $name;
            }
        }

        \usort($versions, 'version_compare');

        return $versions;
    }

    /**
     * مسیر ارتقا از `from` تا `to` (شامل هر دو انتها — از خودِ from شروع نمی‌شود).
     *
     * @return list<string> مثل ['1.0.1', '1.1.0', '1.2.0']
     *
     * @throws UpgradeException مسیر وجود نداشته باشد
     */
    public function upgradePath(string $from, string $to): array
    {
        self::assertSemver($from);
        self::assertSemver($to);

        if (\version_compare($to, $from, '<=')) {
            throw UpgradeException::noPath($from, $to);
        }

        $versions = $this->availableVersions();
        $path = [];
        $current = $from;

        foreach ($versions as $version) {
            if (\version_compare($version, $current, '>') && \version_compare($version, $to, '<')) {
                $path[] = $version;
                $current = $version;
            }
        }

        $path[] = $to;   // نسخهٔ هدف همیشه آخرین گام است (حتی بدون دایرکتوری patch)

        return $path;
    }

    /** کوچک‌ترین نسخهٔ موجود بزرگ‌تر از current (برای status/check). */
    public function nextVersion(string $current): ?string
    {
        foreach ($this->availableVersions() as $version) {
            if (\version_compare($version, $current, '>')) {
                return $version;
            }
        }

        return null;
    }

    public static function isSemver(string $version): bool
    {
        return \preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z\-.]+)?(\+[0-9A-Za-z\-.]+)?$/', $version) === 1;
    }

    private static function assertSemver(string $version): void
    {
        if (!self::isSemver($version)) {
            throw UpgradeException::invalidVersion($version);
        }
    }
}

<?php

/**
 * تَوان | Tavaan — Bootstrap
 * تنها فایل require دستی مجاز در Entry Pointها (public/index.php و bin/tvn).
 *
 * زنجیره: ثابت‌ها → بررسی نسخهٔ PHP → Autoloader → EarlyErrorManager → config/autoload.
 *
 * @see docs/02-architecture/bootstrapping.md
 */

declare(strict_types=1);

use Tavaan\Core\Autoloading\Autoloader;
use Tavaan\Core\Autoloading\AutoloaderRegistry;
use Tavaan\Core\Error\EarlyErrorManager;

// ── ۰) idempotent — اگر قبلاً بوت شده، فقط Autoloader را برگردان ─────────
if (\defined('TVN_BOOTSTRAPPED')) {
    return \Tavaan\Core\Autoloading\AutoloaderRegistry::get();
}

// ── ۱) ثابت‌ها ───────────────────────────────────────────────────────────
\defined('TVN_VERSION')   || \define('TVN_VERSION', '0.1.0');
\defined('TVN_START')     || \define('TVN_START', \microtime(true));
\defined('TVN_BASE_PATH') || \define('TVN_BASE_PATH', \dirname(__DIR__));

// ── ۲) بررسی حداقل نسخهٔ PHP ─────────────────────────────────────────────
if (PHP_VERSION_ID < 80200) {
    \fwrite(STDERR, 'Tavaan requires PHP >= 8.2.0. Current: ' . PHP_VERSION . PHP_EOL);
    exit(1);
}

// ── ۳) تنظیمات اولیهٔ خطا ────────────────────────────────────────────────
\error_reporting(E_ALL);
\ini_set('display_errors', '0');
\ini_set('log_errors', '1');
\ini_set('log_errors_max_len', '0');

// ── ۴) ثبت Autoloader ────────────────────────────────────────────────────
require_once TVN_BASE_PATH . '/core/Autoloading/Autoloader.php';

$autoloader = Autoloader::create(TVN_BASE_PATH);

// نگاشت هسته (Hardcoded تا قبل از خواندن config)
$autoloader->addNamespace('Tavaan\\Core\\', 'core/');
$autoloader->addNamespace('Tavaan\\Support\\', 'core/Support/');
$autoloader->addNamespace('Tavaan\\Engine\\', 'engines/*/src/');
$autoloader->register();

// 🔒 prefixهای هسته قفل می‌شوند (غیرقابل override توسط ماژول/پلاگین)
$autoloader->lockPrefix('Tavaan\\Core\\');
$autoloader->lockPrefix('Tavaan\\Support\\');
$autoloader->lockPrefix('Tavaan\\Engine\\');

// ── ۵) مدیریت خطای زودهنگام ──────────────────────────────────────────────
EarlyErrorManager::register();

// ── ۶) بارگذاری نگاشت کامل از config (اگر موجود) ─────────────────────────
$configFile = TVN_BASE_PATH . '/config/autoload.php';
if (\is_file($configFile)) {
    /** @var array<string, mixed> $autoloadConfig */
    $autoloadConfig = require $configFile;

    foreach ($autoloadConfig['psr-4'] ?? [] as $prefix => $paths) {
        $autoloader->addNamespace((string) $prefix, $paths);
    }

    foreach ($autoloadConfig['locked_prefixes'] ?? [] as $locked) {
        $autoloader->lockPrefix((string) $locked);
    }

    $classMapCache = TVN_BASE_PATH . '/' . ($autoloadConfig['cache']['file'] ?? 'storage/cache/classmap.php');
    if ((bool) ($autoloadConfig['cache']['enabled'] ?? false) && \is_file($classMapCache)) {
        /** @var array<string, string> $classMap */
        $classMap = require $classMapCache;
        $autoloader->addClassMap($classMap, authoritative: true);
    }

    foreach ($autoloadConfig['files'] ?? [] as $file) {
        $autoloader->addFile((string) $file);
    }

    $autoloader->loadFiles();
}

// ── ۷) در دسترس قرار دادن Autoloader ─────────────────────────────────────
AutoloaderRegistry::set($autoloader);

\define('TVN_BOOTSTRAPPED', true);

return $autoloader;

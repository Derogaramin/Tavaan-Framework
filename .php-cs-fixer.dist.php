<?php

/**
 * dev-only — قواعد DOC-011 (PSR-12 + افزودنی‌های تَوان).
 */

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/core', __DIR__ . '/engines'])
    ->exclude(['tests', 'benchmarks'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'global_namespace_import' => ['import_classes' => null, 'import_functions' => null],   // U-02: توابع با \func()؛ کلاس‌های داخلی (\Closure/…) به‌سبک رایج کد‌بیس inline-FQ می‌مانند (تصمیم ممیزی ۲۰۲۶-۱۰-۱۶، واگرا با DOC-011 §7/import_classes=true)
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced', 'strict' => false],   // U-02: \ برای توابع native — strict:false یعنی به \funcهای غیرnative (مانند helperها) دست نزن
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'parameters']],
        'line_ending' => true,
        'no_trailing_whitespace' => true,
        'single_blank_line_at_eof' => true,
    ])
    ->setFinder($finder);

<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Scaffold
 * ScaffoldException — خطای تولید کد (نام نامعتبر، path traversal، stub مفقود).
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class ScaffoldException extends TavaanException
{
    public static function invalidName(string $name, string $expected): self
    {
        return new self(
            messageId: 'scaffold.invalid_name',
            context: ['name' => $name, 'expected' => $expected],
            hint: 'نام را اصلاح کنید؛ فقط حروف/ارقام مجاز و بدون جداکنندهٔ غیراستاندارد.',
            docsUrl: '03-engines/scaffold.html',
        );
    }

    public static function pathTraversal(string $path): self
    {
        return new self(
            messageId: 'scaffold.path_traversal',
            context: ['path' => $path],
            hint: 'مسیر باید نسبی و بدون `..` باشد و داخل ریشهٔ پروژه بماند.',
            docsUrl: '03-engines/scaffold.html',
        );
    }

    public static function stubMissing(string $stub): self
    {
        return new self(
            messageId: 'scaffold.stub_missing',
            context: ['stub' => $stub],
            hint: 'قالب را در resources/stubs/ بررسی کنید.',
            docsUrl: '03-engines/scaffold.html',
        );
    }

    public static function generatorMissing(string $key): self
    {
        return new self(
            messageId: 'scaffold.generator_missing',
            context: ['key' => $key],
            hint: 'کلید Generator در ScaffoldServiceProvider ثبت می‌شود.',
            docsUrl: '03-engines/scaffold.html',
        );
    }
}

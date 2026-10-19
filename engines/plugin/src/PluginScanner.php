<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin;

/**
 * تَوان | Tavaan — Plugin
 * PluginScanner — اسکن توکنی توابع خطرناک پیش از نصب (DOC-079 §5.1).
 *
 * فقط فراخوانی‌های «تابع سراسری» پرچم می‌خورند: `->exec()`، `Foo::system()`
 * یا `function exec()` با نگاه‌داشتن توکن قبلی تشخیص و رد می‌شوند
 * (کاهش مثبت کاذب). `include/require` با توکن‌های اختصاصی PHP گرفته می‌شوند.
 *
 * 🔒 یافته → هشدار در نصب + نیازمند تأیید صریح admin (`--force`).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginScanner
{
    /** توابع ممنوعه (DOC-079 §5.1) */
    private const DANGEROUS = [
        'eval', 'exec', 'system', 'passthru', 'shell_exec', 'popen', 'proc_open',
        'assert', 'create_function', 'unserialize', 'file_get_contents', 'fopen',
        'curl_exec',
    ];

    /** توکن‌های زبانی include/require */
    private const INCLUDE_TOKENS = ['T_INCLUDE', 'T_INCLUDE_ONCE', 'T_REQUIRE', 'T_REQUIRE_ONCE'];

    /** توکن‌هایی که بعد از آن‌ها T_STRING «فراخوانی تابع سراسری» نیست */
    private const SAFE_PRECEDING = ['T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR', 'T_DOUBLE_COLON', 'T_FUNCTION', 'T_CONST', 'T_STRING'];

    /**
     * اسکن همهٔ فایل‌های PHP یک دایرکتوری (بازگشتی).
     *
     * 🔒 `vendor/` مستثنی است: autoload خودِ composer از require/include
     * استفاده می‌کند و اسکن آن مثبت کاذب انبوه می‌سازد؛ یکپارچگی vendor با
     * hashهای plugins.lock محافظت می‌شود (DOC-079 §4).
     */
    public function scan(string $dir): ScanReport
    {
        $findings = [];
        $files = $this->phpFiles($dir);

        foreach ($files as $file) {
            $contents = \file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            $tokens = \token_get_all($contents);
            $previous = null;
            foreach ($tokens as $token) {
                if (\is_string($token)) {
                    // این جداکننده‌ها «زمینهٔ عضویت» را باطل می‌کنند:
                    // بعد از `;` `}` `{` `,` `)` یک T_STRING قطعاً فراخوانی تابع است
                    if (\in_array($token, ['(', ')', ';', '{', '}', ','], true)) {
                        $previous = null;
                    }
                    continue;
                }

                [$id, $text, $line] = $token;
                $tokenName = \token_name($id);

                if (\in_array($tokenName, self::INCLUDE_TOKENS, true)) {
                    $findings[] = ['file' => $file, 'line' => $line, 'func' => \strtolower($text)];
                    $previous = $tokenName;
                    continue;
                }

                if ($tokenName === 'T_EVAL') {
                    $findings[] = ['file' => $file, 'line' => $line, 'func' => 'eval'];
                    $previous = $tokenName;
                    continue;
                }

                if ($tokenName === 'T_STRING') {
                    $lower = \strtolower($text);
                    $dangerous = \in_array($lower, self::DANGEROUS, true);
                    $callableNext = true;   // سخت‌گیری نهایی با توکن بعدی ممکن نیست؛ previous کافی است
                    if ($dangerous && $callableNext && !$this->isMemberContext($previous)) {
                        $findings[] = ['file' => $file, 'line' => $line, 'func' => $lower];
                    }
                }

                if ($tokenName !== 'T_WHITESPACE' && $tokenName !== 'T_COMMENT' && $tokenName !== 'T_DOC_COMMENT') {
                    $previous = $tokenName;
                }
            }
        }

        return new ScanReport($findings, \count($files));
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        if (!\is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || !\str_ends_with(\strtolower($file->getFilename()), '.php')) {
                continue;
            }
            $full = \str_replace('\\', '/', $file->getPathname());
            if (\str_contains($full, '/vendor/')) {
                continue;   // vendor افزونه → مستثنی (یکپارچگی با lock hash)
            }
            $files[] = $full;
        }
        \sort($files);   // 🔒 ترتیب قطعی گزارش

        return $files;
    }

    private function isMemberContext(?string $previousTokenName): bool
    {
        return $previousTokenName !== null && \in_array($previousTokenName, self::SAFE_PRECEDING, true);
    }
}

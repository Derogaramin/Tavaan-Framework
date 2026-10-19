<?php

declare(strict_types=1);

namespace Tavaan\Testing;

/**
 * تَوان | Tavaan — Testing
 * AssertionFailed — شکست یک Assertion (توسط Runner گرفته می‌شود).
 *
 * محل وقوع (فایل:خطِ اولین فریم بیرون از چارچوب تست) را همراه خود نگه می‌دارد
 * تا گزارش شکست دقیق باشد (AC-TEST-05 — DOC-084E §7).
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class AssertionFailed extends \RuntimeException
{
    private string $location = '';

    public static function for(string $message): self
    {
        $error = new self($message);
        $error->location = self::findTestLocation($error->getTrace());

        return $error;
    }

    /** محل وقوع شکست (`file:line`) — رشتهٔ خالی اگر نامعلوم. */
    public function location(): string
    {
        return $this->location;
    }

    /**
     * اولین فریم بیرون از کد پیاده‌سازی فریمورک = محل واقعی Assertion در کد
     * کاربر/تست. (core/، engines/*‌/src/، bin/ و vendor/ رد می‌شوند تا کلاس‌های
     * تست در هر فضای‌نامی — از جمله Tavaan\Testing\Tests — درست گزارش شوند.)
     *
     * @param list<array<string, mixed>> $trace
     */
    private static function findTestLocation(array $trace): string
    {
        foreach ($trace as $frame) {
            $file = \str_replace('\\', '/', (string) ($frame['file'] ?? ''));
            if ($file === '') {
                continue;
            }
            if (\preg_match('#/(core|vendor|engines/[^/]+/src)/#', $file) === 1) {
                continue;
            }
            if (\preg_match('#/(bin|tools)/#', $file) === 1) {
                continue;
            }

            return $file . ':' . (int) ($frame['line'] ?? 0);
        }

        return '';
    }
}

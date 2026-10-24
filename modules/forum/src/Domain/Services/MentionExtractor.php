<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * MentionExtractor — استخراج @username از بدنهٔ پست (DOC-094 §6).
 */

namespace Modules\Forum\Domain\Services;

/**
 * MentionExtractor — دامنهٔ خالص.
 *
 * قواعد:
 * - `@username` با حروف لاتین/فارسی/رقم/`_` (حداقل ۳ کاراکتر)
 * - داخل code block/backtick شمارش نمی‌شود
 * - خروجی: فهرست یکتا با حفظ ترتیب، با سقف `max_per_post`
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class MentionExtractor
{
    public function __construct(private readonly int $maxPerPost = 10)
    {
    }

    /**
     * @return list<string> usernameها (بدون @)
     */
    public function extract(string $body): array
    {
        // حذف code fence و inline code تا mention داخل کد شمارش نشود
        $clean = (string) \preg_replace('/```.*?```/s', ' ', $body);
        $clean = (string) \preg_replace('/`[^`]*`/', ' ', $clean);

        if (\preg_match_all('/(?:^|[\s(>،,;:!؟?])@([\p{L}][\p{L}\p{Nd}_]{2,39})/u', $clean, $m) === false) {
            return [];
        }

        $seen = [];
        $out = [];
        foreach ((array) ($m[1] ?? []) as $username) {
            $username = (string) $username;
            $key = \mb_strtolower($username);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $username;
            }
            if (\count($out) >= $this->maxPerPost) {
                break;
            }
        }

        return $out;
    }
}

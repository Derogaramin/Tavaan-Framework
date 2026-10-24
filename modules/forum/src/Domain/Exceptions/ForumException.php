<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ForumException — خطاهای دامنهٔ انجمن (flood/spam/ban/lock).
 */

namespace Modules\Forum\Domain\Exceptions;

/**
 * ForumException — کنترلرها این را می‌گیرند و به flash-message تبدیل
 * می‌کنند (۴xx)؛ کد ماشین‌خوان برای API/تست.
 *
 * @api 1.0
 */
final class ForumException extends \RuntimeException
{
    /** @param array<string, mixed> $context */
    private function __construct(string $message, private readonly string $reason, private readonly array $context = [])
    {
        parent::__construct($message);
    }

    public static function floodBlocked(int $retryAfter, string $reason = 'min_interval'): self
    {
        return new self(
            'برای ارسال بعدی کمی صبر کنید (ضد-flood).',
            'flood',
            ['retry_after' => $retryAfter, 'rule' => $reason],
        );
    }

    public static function spamDetected(): self
    {
        return new self('پیام شما به‌عنوان اسپم شناسایی شد.', 'spam');
    }

    public static function honeypot(): self
    {
        return new self('ارسال نامعتبر (honeypot).', 'honeypot');
    }

    public static function banned(?string $reason = null): self
    {
        return new self(
            'حساب شما در انجمن مسدود است.' . ($reason !== null && $reason !== '' ? ' دلیل: ' . $reason : ''),
            'banned',
        );
    }

    public static function forumLocked(): self
    {
        return new self('این تالار قفل است و موضوع جدید نمی‌پذیرد.', 'forum_locked');
    }

    public static function threadLocked(): self
    {
        return new self('این موضوع قفل است و پاسخ جدید نمی‌پذیرد.', 'thread_locked');
    }

    public static function alreadyFlagged(): self
    {
        return new self('شما پیش‌تر این پست را گزارش کرده‌اید.', 'already_flagged');
    }

    public static function cannotVoteOwnPost(): self
    {
        return new self('نمی‌توانید به پست خودتان رأی دهید.', 'own_post');
    }

    public static function solutionNotAllowed(): self
    {
        return new self('فقط نویسندهٔ موضوع (یا moderator) می‌تواند پاسخ برگزیده انتخاب کند.', 'solution_forbidden');
    }

    public static function guestNotAllowed(): self
    {
        return new self('برای این عملیات باید وارد شوید.', 'guest');
    }

    public static function postNotVisible(): self
    {
        return new self('این پست در دسترس نیست.', 'post_not_visible');
    }

    public static function forumHasThreads(): self
    {
        return new self('تالار دارای موضوع است؛ نخست موضوعات را جابجا یا حذف کنید.', 'forum_not_empty');
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
